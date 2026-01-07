<?php

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class AddressInput
{
    #[Assert\NotBlank]
    public string $name = '';

    #[SerializedName('client_name')]
    #[Assert\NotBlank]
    public string $clientName = '';

    #[SerializedName('address_type')]
    #[Assert\NotBlank]
    public string $addressType = '';

    #[Assert\NotBlank]
    public string $street = '';

    #[SerializedName('code_postal')]
    #[Assert\NotBlank]
    public string $codePostal = '';

    #[Assert\NotBlank]
    public string $city = '';

    #[Assert\NotBlank]
    public string $state = '';

    #[SerializedName('more_details')]
    #[Assert\Length(max: 1000)]
    public ?string $moreDetails = null;
}
