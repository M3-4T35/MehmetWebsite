<?php

namespace App\Form;

use App\Entity\Media;
use App\Entity\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class MediaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('imageFile', FileType::class, [
                'label' => 'Fichier média',
                'mapped' => false,
                'required' => !$options['is_edit'],
                'help' => 'Images (jpg, png, gif, webp) ou vidéos (mp4, webm, ogg) — 250 Mo maximum.',
                'constraints' => [
                    new File([
                        'maxSize' => '250M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                            'video/mp4',
                            'video/webm',
                            'video/ogg',
                        ],
                        'mimeTypesMessage' => 'Formats acceptés : jpg, png, gif, webp, mp4, webm, ogg.',
                    ]),
                ],
            ])
            ->add('alt')
            ->add('position')
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'title',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Media::class,
            'is_edit' => false,
        ]);
    }
}
