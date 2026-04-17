<?php

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO de validation pour la création ou la modification d'une adresse via l'API.
 */
final class AddressInput
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 80)]
    public string $name = '';

    #[SerializedName('client_name')]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 120)]
    // Lettres + espaces + ponctuation simple (évite les caractères bizarres)
    #[Assert\Regex(
        pattern: "/^[\p{L}\p{M}][\p{L}\p{M}\s'’\.\-]{1,119}$/u",
        message: "Nom du destinataire invalide."
    )]
    public string $clientName = '';

    #[SerializedName('address_type')]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['facturation', 'livraison'])]
    public string $addressType = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 150)]
    // Autorise numéros, lettres, accents, /, -, ', etc.
    #[Assert\Regex(
        pattern: "/^[0-9\p{L}\p{M}][0-9\p{L}\p{M}\s'’\.\,\-\/°#]{3,149}$/u",
        message: "Adresse (rue) invalide."
    )]
    public string $street = '';

    #[SerializedName('code_postal')]
    #[Assert\NotBlank]
    // France métropolitaine: 5 chiffres (ex: 75001). Simple et efficace.
    #[Assert\Regex(
        pattern: "/^\d{5}$/",
        message: "Code postal invalide (attendu: 5 chiffres)."
    )]
    public string $codePostal = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 80)]
    #[Assert\Regex(
        pattern: "/^[\p{L}\p{M}][\p{L}\p{M}\s'’\.\-]{1,79}$/u",
        message: "Ville invalide."
    )]
    public string $city = '';

    #[Assert\NotBlank]
    // Tu stockes un code pays (alpha2). Ex: FR, BE...
    #[Assert\Regex(
        pattern: "/^[A-Z]{2}$/",
        message: "Pays invalide."
    )]
    public string $state = 'FR';

    #[SerializedName('more_details')]
    #[Assert\Length(max: 500)]
    public ?string $moreDetails = null;
}
