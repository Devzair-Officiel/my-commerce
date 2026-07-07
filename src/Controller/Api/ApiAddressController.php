<?php

namespace App\Controller\Api;

use App\Entity\Address;
use App\Entity\User;
use App\Dto\AddressInput;
use App\Security\AddressVoter;
use App\Repository\AddressRepository;
use App\Service\AddressArraySerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * API JSON pour la gestion des adresses d'un utilisateur connecté (création, modification, suppression).
 * Protège les opérations avec un voter dédié et une validation CSRF.
 */
#[Route('/api/address')]
class ApiAddressController extends AbstractController
{
    private const CSRF_ID = 'address_api';

    #[Route('', name: 'api_address_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        AddressRepository $addressRepo,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        AddressArraySerializer $out,
        CsrfTokenManagerInterface $csrf,
    ): Response {
        if ($resp = $this->validateCsrf($request, $csrf)) {
            return $resp;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'isSuccess' => false,
                'message' => 'Vous devez être connecté',
                'data' => [],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $input = $serializer->deserialize($request->getContent(), AddressInput::class, 'json');
        $violations = $validator->validate($input);
        if (count($violations) > 0) {
            return $this->json([
                'isSuccess' => false,
                'message' => (string) $violations,
                'data' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $address = (new Address())
            ->setUser($user) // association auto
            ->setName($input->name)
            ->setClientName($input->clientName)
            ->setAddressType($input->addressType)
            ->setStreet($input->street)
            ->setCodePostal($input->codePostal)
            ->setCity($input->city)
            ->setState($input->state)
            ->setMoreDetails($input->moreDetails);

        $em->persist($address);
        $em->flush();

        $addresses = $addressRepo->findByUser($user);

        return $this->json([
            'isSuccess' => true,
            'message' => 'Adresse ajoutée avec succès',
            'data' => $out->many($addresses),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'api_address_update', methods: ['PUT'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        AddressRepository $addressRepo,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        AddressArraySerializer $out,
        CsrfTokenManagerInterface $csrf,
    ): Response {
        if ($resp = $this->validateCsrf($request, $csrf)) {
            return $resp;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'isSuccess' => false,
                'message' => 'Vous devez être connecté',
                'data' => [],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $address = $addressRepo->find($id);
        if (!$address) {
            return $this->json([
                'isSuccess' => false,
                'message' => 'Adresse introuvable',
                'data' => [],
            ], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(AddressVoter::EDIT, $address);

        $input = $serializer->deserialize($request->getContent(), AddressInput::class, 'json');
        $violations = $validator->validate($input);
        if (count($violations) > 0) {
            return $this->json([
                'isSuccess' => false,
                'message' => (string) $violations,
                'data' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $address
            ->setName($input->name)
            ->setClientName($input->clientName)
            ->setAddressType($input->addressType)
            ->setStreet($input->street)
            ->setCodePostal($input->codePostal)
            ->setCity($input->city)
            ->setState($input->state)
            ->setMoreDetails($input->moreDetails);

        $em->flush();

        $addresses = $addressRepo->findByUser($user);

        return $this->json([
            'isSuccess' => true,
            'message' => 'Adresse modifiée avec succès',
            'data' => $out->many($addresses),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'api_address_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        AddressRepository $addressRepo,
        AddressArraySerializer $out,
        CsrfTokenManagerInterface $csrf,
    ): Response {
        if ($resp = $this->validateCsrf($request, $csrf)) {
            return $resp;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'isSuccess' => false,
                'message' => 'Vous devez être connecté',
                'data' => [],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $address = $addressRepo->find($id);
        if (!$address) {
            return $this->json([
                'isSuccess' => false,
                'message' => 'Adresse introuvable',
                'data' => [],
            ], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(AddressVoter::DELETE, $address);

        $em->remove($address);
        $em->flush();

        $addresses = $addressRepo->findByUser($user);

        return $this->json([
            'isSuccess' => true,
            'message' => 'Adresse supprimée avec succès',
            'data' => $out->many($addresses),
        ]);
    }

    private function validateCsrf(Request $request, CsrfTokenManagerInterface $csrf): ?Response
    {
        $tokenValue = $request->headers->get('X-CSRF-TOKEN');
        if (!$tokenValue) {
            return $this->json([
                'isSuccess' => false,
                'message' => 'CSRF token manquant',
                'data' => [],
            ], Response::HTTP_FORBIDDEN);
        }

        $token = new CsrfToken(self::CSRF_ID, $tokenValue);
        if (!$csrf->isTokenValid($token)) {
            return $this->json([
                'isSuccess' => false,
                'message' => 'CSRF token invalide',
                'data' => [],
            ], Response::HTTP_FORBIDDEN);
        }

        // Comparaison stricte : str_starts_with laisserait passer
        // https://site.com.evil.com quand le site est https://site.com
        $origin = $request->headers->get('Origin');
        if ($origin && $origin !== $request->getSchemeAndHttpHost()) {
            return $this->json([
                'isSuccess' => false,
                'message' => 'Origin invalide',
                'data' => [],
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
