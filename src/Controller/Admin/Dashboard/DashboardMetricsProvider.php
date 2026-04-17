<?php

namespace App\Admin\Dashboard;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\Contact;
use App\Enum\PaymentStatus;
use Doctrine\DBAL\Types\Types;
use App\Enum\FulfillmentStatus;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Calcule et met en cache les métriques du tableau de bord administrateur
 * (commandes, chiffre d'affaires, nouveaux clients, graphiques sur 14 jours).
 */
final class DashboardMetricsProvider
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
    ) {}

    public function getMetrics(): array
    {
        return $this->cache->get('admin.dashboard.metrics', function (ItemInterface $item) {
            $item->expiresAfter(60);

            $now = new \DateTimeImmutable('now');
            $since7 = $now->modify('-7 days');
            $since30 = $now->modify('-30 days');
            $since14 = $now->modify('-13 days'); // 14 points (aujourd’hui inclus)
            $from30 = $now->modify('-30 days');
            $from60 = $now->modify('-60 days');
            $monthStart = $now->modify('first day of this month')->setTime(0, 0);
            $nextMonthStart = $monthStart->modify('+1 month');

            $usersThisMonth = $this->countUsersCreatedBetween($monthStart, $nextMonthStart);

            $paid30 = $this->countOrdersPaidBetween($from30, $now);
            $paidPrev30 = $this->countOrdersPaidBetween($from60, $from30);

            $revenue30 = $this->sumRevenuePaidBetween($from30, $now);
            $revenuePrev30 = $this->sumRevenuePaidBetween($from60, $from30);

            $avgBasket30 = $paid30 > 0 ? (int) round($revenue30 / $paid30) : 0;
            $avgBasketPrev30 = $paidPrev30 > 0 ? (int) round($revenuePrev30 / $paidPrev30) : 0;

            $paid30Trend = $this->trendPercent($paid30, $paidPrev30);
            $revenue30Trend = $this->trendPercent($revenue30, $revenuePrev30);
            $avgBasket30Trend = $this->trendPercent($avgBasket30, $avgBasketPrev30);

            $paid7 = $this->countOrdersPaidSince($since7);
            $paid30 = $this->countOrdersPaidSince($since30);

            $revenue7 = $this->sumRevenuePaidSince($since7);
            $revenue30 = $this->sumRevenuePaidSince($since30);

            $avgBasket30 = $paid30 > 0 ? (int) round($revenue30 / $paid30) : 0;

            $pending = $this->countByPaymentStatus(PaymentStatus::Pending);
            $failed = $this->countByPaymentStatus(PaymentStatus::Failed);
            $refunded = $this->countByPaymentStatus(PaymentStatus::Refunded);

            $fulfillmentBreakdown = $this->countByFulfillmentStatus();
            $recentOrders = $this->recentOrders(10);
            $lowStock = $this->lowStockProducts(8, 5);

            $recentImportantContacts = $this->recentImportantContacts(5);

            // ✅ Séries pour Chart.js (PostgreSQL)
            $chart = $this->paidSeriesByDay($since14, $now);

            return [
                'kpis' => [
                    'paid7' => $paid7,
                    'paid30' => $paid30,
                    'revenue7Cents' => $revenue7,
                    'revenue30Cents' => $revenue30,
                    'avgBasket30Cents' => $avgBasket30,
                    'pendingPayments' => $pending,
                    'failedPayments' => $failed,
                    'refundedPayments' => $refunded,
                    'fulfillment' => $fulfillmentBreakdown,
                    'paid30Trend' => $paid30Trend,
                    'revenue30Trend' => $revenue30Trend,
                    'avgBasket30Trend' => $avgBasket30Trend,
                    'usersThisMonth' => $usersThisMonth,
                ],
                'recentOrders' => $recentOrders,
                'lowStock' => $lowStock,
                'recentImportantContacts' => $recentImportantContacts,
                'charts' => $chart,
            ];
        });
    }

    /**
     * @return array{
     *   labels: list<string>,
     *   paidCount: list<int>,
     *   paidRevenueCents: list<int>
     * }
     */
    private function paidSeriesByDay(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        // Génère la liste complète des jours, même si aucun paiement
        $labels = [];
        $cursor = $from->setTime(0, 0);
        $end = $to->setTime(0, 0);

        while ($cursor <= $end) {
            $labels[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        $paidValue = PaymentStatus::Paid->value;

        // PostgreSQL : date_trunc('day', paid_at)
        $sql = <<<SQL
                SELECT
                to_char(date_trunc('day', paid_at), 'YYYY-MM-DD') AS d,
                COUNT(*)::int AS c,
                COALESCE(SUM(order_total_ttc_cents), 0)::int AS s
                FROM "order"
                WHERE payment_status = :paid
                AND paid_at IS NOT NULL
                AND paid_at >= :from
                AND paid_at < :toPlus
                GROUP BY d
                ORDER BY d ASC
            SQL;

        $toPlus = $end->modify('+1 day'); // borne exclusive

        $rows = $this->em->getConnection()->fetchAllAssociative(
            $sql,
            [
                'paid' => $paidValue,
                'from' => $from,
                'toPlus' => $toPlus,
            ],
            [
                'paid' => ParameterType::STRING,
                'from' => Types::DATETIME_IMMUTABLE,
                'toPlus' => Types::DATETIME_IMMUTABLE,
            ]
        );

        $mapCount = [];
        $mapSum = [];
        foreach ($rows as $r) {
            $day = (string) $r['d'];
            $mapCount[$day] = (int) $r['c'];
            $mapSum[$day] = (int) $r['s'];
        }

        $paidCount = [];
        $paidRevenueCents = [];
        foreach ($labels as $day) {
            $paidCount[] = $mapCount[$day] ?? 0;
            $paidRevenueCents[] = $mapSum[$day] ?? 0;
        }

        return [
            'labels' => $labels,
            'paidCount' => $paidCount,
            'paidRevenueCents' => $paidRevenueCents,
        ];
    }

    private function countOrdersPaidBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(\App\Entity\Order::class, 'o')
            ->andWhere('o.paymentStatus = :paid')
            ->andWhere('o.paidAt >= :from')
            ->andWhere('o.paidAt < :to')
            ->setParameter('paid', \App\Enum\PaymentStatus::Paid)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function sumRevenuePaidBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(o.orderTotalTtcCents), 0)')
            ->from(\App\Entity\Order::class, 'o')
            ->andWhere('o.paymentStatus = :paid')
            ->andWhere('o.paidAt >= :from')
            ->andWhere('o.paidAt < :to')
            ->setParameter('paid', \App\Enum\PaymentStatus::Paid)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }


    private function countOrdersPaidSince(\DateTimeImmutable $since): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(Order::class, 'o')
            ->andWhere('o.paymentStatus = :paid')
            ->andWhere('o.paidAt >= :since')
            ->setParameter('paid', PaymentStatus::Paid)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function sumRevenuePaidSince(\DateTimeImmutable $since): int
    {
        $result = $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(o.orderTotalTtcCents), 0)')
            ->from(Order::class, 'o')
            ->andWhere('o.paymentStatus = :paid')
            ->andWhere('o.paidAt >= :since')
            ->setParameter('paid', PaymentStatus::Paid)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    private function countByPaymentStatus(PaymentStatus $status): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(Order::class, 'o')
            ->andWhere('o.paymentStatus = :st')
            ->setParameter('st', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string,int> keyed by fulfillment status value
     */
    private function countByFulfillmentStatus(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('o.fulfillmentStatus AS st, COUNT(o.id) AS c')
            ->from(Order::class, 'o')
            ->groupBy('o.fulfillmentStatus')
            ->getQuery()
            ->getArrayResult();

        $out = [];

        foreach ($rows as $row) {
            /** @var FulfillmentStatus $status */
            $status = $row['st'];

            // 🔒 NORMALISATION ENUM → STRING
            $out[$status->value] = (int) $row['c'];
        }

        // 🔒 Garantir toutes les clés (même à 0)
        foreach (FulfillmentStatus::cases() as $case) {
            $out[$case->value] = $out[$case->value] ?? 0;
        }

        return $out;
    }

    private function countUsersCreatedBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(\App\Entity\User::class, 'u')
            ->andWhere('u.createdAt >= :from')
            ->andWhere('u.createdAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }


    /**
     * @return list<array{
     *   id:int,
     *   email:string|null,
     *   totalCents:int,
     *   currency:string|null,
     *   paymentStatus:string|null,
     *   fulfillmentStatus:string|null,
     *   paidAt:\DateTimeInterface|null
     * }>
     */
    private function recentOrders(int $limit): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('o.id AS id')
            ->addSelect('u.email AS email')
            ->addSelect('o.orderTotalTtcCents AS totalCents')
            ->addSelect('o.currency AS currency')
            ->addSelect('o.paymentStatus AS paymentStatus')
            ->addSelect('o.fulfillmentStatus AS fulfillmentStatus')
            ->addSelect('o.paidAt AS paidAt')
            ->from(Order::class, 'o')
            ->leftJoin('o.user', 'u')
            ->orderBy('o.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['totalCents'] = (int) $r['totalCents'];

            // 🔒 NORMALISATION ENUM → STRING
            if ($r['paymentStatus'] instanceof \BackedEnum) {
                $r['paymentStatus'] = $r['paymentStatus']->value;
            }

            if ($r['fulfillmentStatus'] instanceof \BackedEnum) {
                $r['fulfillmentStatus'] = $r['fulfillmentStatus']->value;
            }
        }

        return $rows;
    }


    /**
     * @return list<array{id:int,title:string,stock:int}>
     */
    private function lowStockProducts(int $limit, int $threshold): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('p.id AS id, p.title AS title, p.stock AS stock')
            ->from(Product::class, 'p')
            ->andWhere('p.stock <= :t')
            ->setParameter('t', $threshold)
            ->orderBy('p.stock', 'ASC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['stock'] = (int) $r['stock'];
        }

        return $rows;
    }

    private function trendPercent(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : null;
        }

        return (($current - $previous) / $previous) * 100;
    }

    /**
     * @return list<array{id:int,email:string|null,subject:string|null,snippet:string,createdAt:string,userEmail:string|null}>
     */
    private function recentImportantContacts(int $limit): array
    {
        $prioritySubjects = [
            'Problème avec une commande reçue',
            'Paiement et facturation',
            'Retour ou échange de produit'
        ];

        $rows = $this->em->createQueryBuilder()
            ->select('c.id AS id')
            ->addSelect('c.email AS email')
            ->addSelect('c.subject AS subject')
            ->addSelect('c.content AS content')
            ->addSelect('c.createdAt AS createdAt')
            ->addSelect('u.email AS userEmail')
            ->from(Contact::class, 'c')
            ->leftJoin('c.user', 'u')
            ->where('c.subject IN (:subjects)')
            ->setParameter('subjects', $prioritySubjects)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $content = $row['content'] ?? '';
            $row['snippet'] = substr(strip_tags($content), 0, 80) . '...';
            $row['createdAt'] = $row['createdAt']->format('d/m/Y H:i');
        }

        return $rows;
    }
}
