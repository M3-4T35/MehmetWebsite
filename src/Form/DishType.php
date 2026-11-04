<?php

namespace App\Form;

use App\Entity\Dish;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

class DishType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $imageConstraints = [
            new File([
                'maxSize' => '250M',
                'mimeTypes' => [
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                    'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
                ],
                'mimeTypesMessage' => 'Formats acceptés : jpg, png, gif, webp, mp4, webm, ogg, mov.',
            ]),
        ];
        if (!$options['is_edit']) {
            $imageConstraints[] = new NotNull(['message' => 'Veuillez choisir une photo ou une vidéo du plat.']);
        }

        $builder
            ->add('imageFile', FileType::class, [
                'label' => 'Photo ou vidéo du plat',
                'mapped' => false,
                'required' => !$options['is_edit'],
                'help' => $options['is_edit']
                    ? 'Laisser vide pour conserver le fichier actuel. Images (jpg, png, gif, webp) ou vidéos (mp4, webm, ogg, mov) — 250 Mo maximum.'
                    : 'Images (jpg, png, gif, webp) ou vidéos (mp4, webm, ogg, mov) — 250 Mo maximum.',
                'attr' => ['accept' => 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/ogg,video/quicktime'],
                'constraints' => $imageConstraints,
            ])
            ->add('title', TextType::class, [
                'label' => 'Nom du plat (FR)',
                'attr' => ['placeholder' => 'Ex. : Mantı maison'],
            ])
            ->add('titleEn', TextType::class, [
                'label' => 'Nom du plat (EN)',
                'required' => false,
                'help' => 'Optionnel — le nom français est utilisé sinon.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (FR)',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Ingrédients, anecdote, recette…'],
            ])
            ->add('descriptionEn', TextareaType::class, [
                'label' => 'Description (EN)',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('position', IntegerType::class, [
                'label' => "Ordre d'affichage",
                'required' => false,
                'help' => 'Les plats avec le plus petit nombre apparaissent en premier.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dish::class,
            'is_edit' => false,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
