<?php

namespace App\Service;

use App\Entity\Address;

/**
 * Convertit une entité Address (ou une collection) en tableau scalaire pour les réponses JSON de l'API.
 */
final class AddressArraySerializer
{
    public function one(Address $a): array
    {
        return [
            'id' => $a->getId(),
            'name' => $a->getName(),
            'client_name' => $a->getClientName(),
            'address_type' => $a->getAddressType(),
            'street' => $a->getStreet(),
            'code_postal' => $a->getCodePostal(),
            'city' => $a->getCity(),
            'state' => $a->getState(),
            'more_details' => $a->getMoreDetails(),
        ];
    }

    /**
     * @param Address[] $addresses
     */
    public function many(array $addresses): array
    {
        return array_map(fn(Address $a) => $this->one($a), $addresses);
    }
}
