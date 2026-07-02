<?php

namespace App\Form;

use App\Entity\Media;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

/**
 * Formulaire d'upload d'un fichier média (image) avec validation de type, taille et champs de métadonnées.
 */
class MediaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('upload', FileType::class, $this->uploadFieldOptions())
            ->add('alt', TextType::class, ['required' => false])
            ->add('position', IntegerType::class, ['required' => false])
            ->add('isCover', CheckboxType::class, [
                'required' => false,
                'label' => 'Image principale',
            ])
            ->add('isWholesale', CheckboxType::class, [
                'required' => false,
                'label' => 'Image grossiste',
                'help' => 'Affichée uniquement sur la fiche vente en gros.',
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $media = $event->getData();
            if (!$media instanceof Media) {
                return;
            }

            $filename = $media->getFilename();
            if (!is_string($filename) || $filename === '') {
                return;
            }

            $baseUrl = $this->resolveBaseUrl($media);
            $thumb   = $media->getThumbnailFilename();

            $previewHtml = sprintf(
                '<div style="margin-top:6px;"><img src="%s/%s" alt="" style="max-height:120px;border-radius:6px;border:1px solid #e5e7eb;" onerror="this.onerror=null;this.src=\'%s/%s\';"></div>',
                htmlspecialchars($baseUrl, ENT_QUOTES),
                htmlspecialchars($thumb, ENT_QUOTES),
                htmlspecialchars($baseUrl, ENT_QUOTES),
                htmlspecialchars($filename, ENT_QUOTES),
            );

            $options = $this->uploadFieldOptions();
            $options['help']      = $previewHtml;
            $options['help_html'] = true;

            $event->getForm()->add('upload', FileType::class, $options);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Media::class]);
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadFieldOptions(): array
    {
        return [
            'label'    => 'Image',
            'required' => false,
            'mapped'   => true,
            'constraints' => [
                new File(
                    maxSize: '5M',
                    mimeTypes: [
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'image/gif',
                    ],
                    mimeTypesMessage: 'Veuillez uploader une image valide (JPEG, PNG, WEBP, GIF)',
                ),
            ],
        ];
    }

    private function resolveBaseUrl(Media $media): string
    {
        if ($media->getProduct() !== null) {
            return '/assets/images/products';
        }

        if (!$media->getBlogs()->isEmpty()) {
            return '/assets/images/blogs';
        }

        return '/assets/images/products';
    }
}
