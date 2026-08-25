<?php

namespace App\Form;

use App\Entity\Project;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', null, ['label' => 'Titre (FR)'])
            ->add('titleEn', null, ['label' => 'Title (EN)'])
            ->add('descriptionCourte', null, ['label' => 'Description Courte (FR)'])
            ->add('descriptionCourteEn', null, ['label' => 'Short Description (EN)'])
            ->add('description', \Symfony\Component\Form\Extension\Core\Type\TextareaType::class, [
                'label' => 'Description FR (Markdown)',
                'attr' => [
                    'rows' => 10,
                ],
            ])
            ->add('descriptionEn', \Symfony\Component\Form\Extension\Core\Type\TextareaType::class, [
                'label' => 'Description EN (Markdown)',
                'attr' => [
                    'rows' => 10,
                ],
            ])
            ->add('githubUrl')
            ->add('productionUrl')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
