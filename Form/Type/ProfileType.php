<?php

namespace Oro\Bundle\UserBundle\Form\Type;

use Oro\Bundle\FlexibleEntityBundle\Manager\FlexibleManager;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;

use Doctrine\ORM\EntityRepository;

use Oro\Bundle\UserBundle\Acl\Manager as AclManager;
use Oro\Bundle\FlexibleEntityBundle\Form\Type\FlexibleType;
use Oro\Bundle\UserBundle\Form\EventListener\ProfileSubscriber;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Form\Type\EmailType;

class ProfileType extends FlexibleType
{
    /**
     * @var AclManager
     */
    protected $aclManager;

    /**
     * @var SecurityContextInterface
     */
    protected $security;

    /**
     * @param FlexibleManager          $flexibleManager flexible manager
     * @param AclManager               $aclManager      ACL manager
     * @param SecurityContextInterface $security        Security context
     */
    public function __construct(FlexibleManager $flexibleManager, AclManager $aclManager, SecurityContextInterface $security)
    {
        parent::__construct($flexibleManager, '');

        $this->aclManager = $aclManager;
        $this->security   = $security;
    }

    /**
     * {@inheritdoc}
     */
    public function addEntityFields(FormBuilderInterface $builder)
    {
        // add default flexible fields
        parent::addEntityFields($builder);

        // user fields
        $builder
            ->addEventSubscriber(new ProfileSubscriber($builder->getFormFactory(), $this->aclManager, $this->security))
            ->add('username', 'text', array(
                'required'       => true,
            ))
            ->add('email', 'email', array(
                'label'          => 'E-mail',
                'required'       => true,
            ))
            ->add('firstName', 'text', array(
                'label'          => 'First name',
                'required'       => true,
            ))
            ->add('lastName', 'text', array(
                'label'          => 'Last name',
                'required'       => true,
            ))
            ->add('birthday', 'oro_date', array(
                'label'          => 'Date of birth',
                'required'       => false,
            ))
            ->add('imageFile', 'file', array(
                'label'          => 'Avatar',
                'required'       => false,
            ))
            ->add('rolesCollection', 'entity', array(
                'label'          => 'Roles',
                'class'          => 'OroUserBundle:Role',
                'property'       => 'label',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('r')
                        ->where('r.role <> :anon')
                        ->setParameter('anon', User::ROLE_ANONYMOUS);
                },
                'multiple'       => true,
                'expanded'       => true,
                'required'       => true,
            ))
            ->add('groups', 'entity', array(
                'class'          => 'OroUserBundle:Group',
                'property'       => 'name',
                'multiple'       => true,
                'expanded'       => true,
                'required'       => false,
            ))
            ->add('plainPassword', 'repeated', array(
                'type'           => 'password',
                'required'       => true,
                'first_options'  => array('label' => 'Password'),
                'second_options' => array('label' => 'Password again'),
            ))
            ->add('emails', 'collection', array(
                'type'           => new EmailType(),
                'allow_add'      => true,
                'allow_delete'   => true,
                'by_reference'   => false,
                'prototype'      => true,
                'prototype_name' => 'tag__name__',
                'label'          => ' '
            ));
    }

    /**
     * Add entity fields to form builder
     *
     * @param FormBuilderInterface $builder
     */
    public function addDynamicAttributesFields(FormBuilderInterface $builder)
    {
        $builder->add('attributes', 'collection', array(
            'type'          => 'oro_user_profile_value',
            'property_path' => 'values',
            'allow_add'     => true,
            'allow_delete'  => true,
            'by_reference'  => false,
            'attr'          => array(
                'data-col'  => 2,
            )
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class'           => $this->flexibleClass,
            'intention'            => 'profile',
            'validation_groups'    => function (FormInterface $form) {
                return $form->getData() && $form->getData()->getId()
                    ? array('Profile', 'Default')
                    : array('Registration', 'Profile', 'Default');
            },
            'extra_fields_message' => 'This form should not contain extra fields: "{{ extra_fields }}"',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'oro_user_profile';
    }
}
