<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Regex;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // PRODUCT NAME
            ->add('Name', TextType::class, [
                'label' => 'Product Name',
                'attr' => [
                    'placeholder' => 'Enter product name',
                ],
                'constraints' => [
                    new NotBlank(message: 'Please enter a product name.'),
                    new Length(
                        max: 150,
                        maxMessage: 'Product name cannot exceed {{ limit }} characters.'
                    ),
                ],
            ])

            // DESCRIPTION
            ->add('description', TextareaType::class, [
                'label' => 'Product Description',
                'attr' => [
                    'placeholder' => 'Enter product description',
                    'rows' => 4,
                ],
                'constraints' => [
                    new NotBlank(message: 'Please enter a product description.'),
                ],
            ])

            // PRICE
            ->add('price', TextType::class, [
                'label' => 'Price',
                'attr' => [
                    'placeholder' => 'Example: 99.99',
                ],
                'constraints' => [
                    new NotBlank(message: 'Please enter a product price.'),
                    new Regex(
                        pattern: '/^\d+(\.\d{1,2})?$/',
                        message: 'Please enter a valid price with up to 2 decimal places.'
                    ),
                ],
            ])

            // STOCK
            ->add('stock', IntegerType::class, [
                'label' => 'Available Stock',
                'attr' => [
                    'placeholder' => 'Enter available stock',
                    'min' => 0,
                ],
                'constraints' => [
                    new NotBlank(message: 'Please enter the available stock.'),
                    new PositiveOrZero(message: 'Stock cannot be negative.'),
                ],
            ])

            // IMAGE FILE UPLOAD
            ->add('imageFile', FileType::class, [
                'label' => 'Import Product Image',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/png,image/jpeg,image/webp,image/svg+xml',
                ],
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/svg+xml',
                        ],
                        mimeTypesMessage: 'Please upload a valid image file (JPEG, PNG, WEBP, or SVG).'
                    ),
                ],
            ])

            // EXISTING / CUSTOM PATH IMAGE (FALLBACK)
            ->add('image', TextType::class, [
                'label' => 'Or Image Path / Filename',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Optional: aceplus-original.png or images/Original.svg',
                ],
            ])

            // CATEGORY
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => fn(Category $category) =>
                    $category->getName() ?? 'Unnamed category',
                'placeholder' => 'Select a category',
                'label' => 'Product Category',
                'constraints' => [
                    new NotBlank(message: 'Please select a category.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
