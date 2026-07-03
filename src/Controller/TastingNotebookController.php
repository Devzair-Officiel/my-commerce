<?php

namespace App\Controller;

use App\Entity\HoneyTasting;
use App\Repository\HoneyTastingRepository;
use App\Service\HoneyTastingSchema;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Carnet de dégustation de miel : formulaire public permettant à un dégustateur
 * (identifié par son nom, unique) de consigner ses évaluations sensorielles.
 * Une même personne peut revenir compléter/mettre à jour sa fiche : le nom
 * agit comme clé de reprise (upsert insensible à la casse).
 */
final class TastingNotebookController extends AbstractController
{
    public function __construct(
        private readonly HoneyTastingSchema     $schema,
        private readonly HoneyTastingRepository $repository,
    ) {}

    #[Route('/carnet-degustation-miel', name: 'app_tasting_notebook', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): Response {
        $honeys   = $this->schema->honeys();
        $sections = $this->schema->sections();

        $formData   = [];
        $tasterName = '';
        $errors     = [];
        $existing   = null;

        // GET ?nom=… → prérempli à partir d'une fiche existante
        if ($request->isMethod('GET') && ($queryName = trim((string) $request->query->get('nom', '')))) {
            $existing = $this->repository->findOneByNameCaseInsensitive($queryName);
            if ($existing !== null) {
                $tasterName = $existing->getTasterName() ?? '';
                $formData   = $existing->getData();
                $this->addFlash('info', 'Fiche de ' . $tasterName . ' rechargée. Vous pouvez la compléter puis enregistrer.');
            } else {
                $tasterName = $queryName;
                $this->addFlash('info', 'Aucune fiche trouvée pour ce nom. Une nouvelle fiche sera créée.');
            }
        }

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token', '');
            if (!$this->isCsrfTokenValid('tasting_notebook', $token)) {
                $errors[] = 'Session expirée. Merci de resoumettre votre fiche.';
            }

            $tasterName = trim((string) $request->request->get('taster_name', ''));
            $formData   = $this->extractSubmittedData($request, $honeys, $sections);

            if (!$errors) {
                $tasting = $this->repository->findOneByNameCaseInsensitive($tasterName);
                $isUpdate = $tasting !== null;
                $tasting ??= new HoneyTasting();
                $tasting->setTasterName($tasterName)->setData($formData);

                $violations = $validator->validate($tasting);
                if (\count($violations) > 0) {
                    foreach ($violations as $v) {
                        $errors[] = $v->getMessage();
                    }
                } else {
                    if (!$isUpdate) {
                        $em->persist($tasting);
                    }
                    $em->flush();

                    $verb = $isUpdate ? 'mise à jour' : 'enregistrée';
                    $this->addFlash('success', 'Merci ' . $tasting->getTasterName() . ', votre fiche a bien été ' . $verb . '. Vous pouvez la retrouver à tout moment en indiquant votre nom.');

                    return $this->redirectToRoute('app_tasting_notebook', ['nom' => $tasting->getTasterName()]);
                }
            }
        }

        return $this->render('tasting_notebook/index.html.twig', [
            'honeys'      => $honeys,
            'sections'    => $sections,
            'formData'    => $formData,
            'tasterName'  => $tasterName,
            'errors'      => $errors,
            'isExisting'  => $existing !== null,
        ]);
    }

    /**
     * Reconstruit la structure { "<honey>__<section>__<field>": string|string[] } à partir
     * de la requête, en filtrant les valeurs sur les options connues pour éviter toute injection.
     *
     * @param array<string, string> $honeys
     * @param list<array<string, mixed>> $sections
     * @return array<string, mixed>
     */
    private function extractSubmittedData(Request $request, array $honeys, array $sections): array
    {
        $out = [];

        foreach ($honeys as $honeySlug => $_name) {
            foreach ($sections as $section) {
                foreach ($section['fields'] as $field) {
                    $key       = $honeySlug . '__' . $section['slug'] . '__' . $field['slug'];
                    $exclusive = $field['exclusive'] ?? false;
                    $allowed   = $field['options'];

                    if ($exclusive) {
                        $value = $request->request->get($key);
                        if (\is_string($value) && \in_array($value, $allowed, true)) {
                            $out[$key] = $value;
                        }
                    } else {
                        $values = $request->request->all($key);
                        $kept = [];
                        foreach ($values as $v) {
                            if (\is_string($v) && \in_array($v, $allowed, true)) {
                                $kept[] = $v;
                            }
                        }
                        if ($kept) {
                            $out[$key] = $kept;
                        }
                    }
                }

                foreach ($section['textareas'] ?? [] as $area) {
                    $key = $honeySlug . '__' . $section['slug'] . '__' . $area['slug'];
                    $text = trim((string) $request->request->get($key, ''));
                    if ($text !== '') {
                        $out[$key] = mb_substr($text, 0, 2000);
                    }
                }
            }
        }

        return $out;
    }
}
