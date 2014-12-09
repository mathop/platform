<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\EventListener;

use Doctrine\ORM\Events;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;

use Symfony\Component\DependencyInjection\Container;

use Oro\Bundle\TestFrameworkBundle\Test\Doctrine\ORM\OrmTestCase;
use Oro\Bundle\EntityExtendBundle\EventListener\DoctrineListener;
use Oro\Bundle\EntityConfigBundle\DependencyInjection\Utils\ServiceLink;

class DoctrineListenerTest extends OrmTestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $enumManager;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $metadataBuilder;

    /** @var AnnotationReader */
    protected $reader;

    /** @var DoctrineListener */
    protected $listener;

    protected function setUp()
    {
        $this->enumManager     = $this->getMock('Oro\Bundle\EntityExtendBundle\Entity\Manager\MultiEnumManager');
        $this->reader          = new AnnotationReader();
        $this->metadataBuilder = $this->getMockBuilder('Oro\Bundle\EntityExtendBundle\ORM\ExtendMetadataBuilder')
            ->disableOriginalConstructor()->getMock();

        $this->listener = new DoctrineListener(
            $this->prepareEnumBuilder($this->metadataBuilder),
            $this->enumManager,
            $this->reader
        );
    }

    protected function tearDown()
    {
        unset($this->listener, $this->enumManager, $this->metadataBuilder, $this->reader);
    }

    /**
     * @dataProvider entitiesProvider
     *
     * @param string      $namespace
     * @param array       $expectedValues
     * @param null|string $expectedException
     */
    public function testProcessDiscriminatorValues($path, $namespace, array $expectedValues, $expectedException = null)
    {
        if (null !== $expectedException) {
            $this->setExpectedException($expectedException);
        }

        $metadataDriver = new AnnotationDriver($this->reader, $path);

        $em = $this->getTestEntityManager();
        $em->getEventManager()->addEventListener(Events::loadClassMetadata, $this->listener);
        $em->getConfiguration()->setMetadataDriverImpl($metadataDriver);
        $em->getConfiguration()->setEntityNamespaces(['Stub' => $namespace]);

        foreach ($expectedValues as $entityName => $data) {
            list($value, $map) = $data;

            $class = $em->getClassMetadata($entityName);
            $this->assertSame($map, $class->discriminatorMap);
            $this->assertSame($value, $class->discriminatorValue);
        }
    }

    /**
     * @return array
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function entitiesProvider()
    {
        $dirPath = rtrim(__DIR__, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR;
        $prefix  = 'Oro\Bundle\EntityExtendBundle\Tests\Unit\EventListener\Fixtures\\';

        return [
            'regular entities, empty map expected'                                           => [
                '$namespace'      => $dirPath . 'Regular',
                '$path'           => $prefix . 'Regular',
                '$expectedValues' => [],
            ],
            'regular entities, should not read values, empty map expected'                   => [
                '$namespace'      => $dirPath . 'RegularWithAnnotation',
                '$path'           => $prefix . 'RegularWithAnnotation',
                '$expectedValues' => [
                    $prefix . 'RegularWithAnnotation\TestComment' => [null, []]
                ],
            ],
            'inherited entities, should work if map set on parent level'                     => [
                '$namespace'      => $dirPath . 'InheritedWithMapOnParent',
                '$path'           => $dirPath . 'InheritedWithMapOnParent',
                '$expectedValues' => [
                    $prefix . 'InheritedWithMapOnParent\BaseEntity'  => [
                        'base',
                        [
                            'base'  => $prefix . 'InheritedWithMapOnParent\BaseEntity',
                            'child' => $prefix . 'InheritedWithMapOnParent\ChildEntity'
                        ]

                    ],
                    $prefix . 'InheritedWithMapOnParent\ChildEntity' => [
                        'child',
                        [
                            'base'  => $prefix . 'InheritedWithMapOnParent\BaseEntity',
                            'child' => $prefix . 'InheritedWithMapOnParent\ChildEntity'
                        ]
                    ]
                ]
            ],
            'inherited entities, should work if map auto generated'                          => [
                '$namespace'      => $dirPath . 'InheritedWithAutogeneratedMap',
                '$path'           => $prefix . 'InheritedWithAutogeneratedMap',
                '$expectedValues' => [
                    $prefix . 'InheritedWithAutogeneratedMap\BaseEntity'  => [
                        'baseentity',
                        [
                            'baseentity'  => $prefix . 'InheritedWithAutogeneratedMap\BaseEntity',
                            'childentity' => $prefix . 'InheritedWithAutogeneratedMap\ChildEntity'
                        ]

                    ],
                    $prefix . 'InheritedWithAutogeneratedMap\ChildEntity' => [
                        'childentity',
                        [
                            'baseentity'  => $prefix . 'InheritedWithAutogeneratedMap\BaseEntity',
                            'childentity' => $prefix . 'InheritedWithAutogeneratedMap\ChildEntity'
                        ]
                    ]
                ]
            ],
            'inherited entities, should collect values set on child level'                   => [
                '$namespace'      => $dirPath . 'InheritedWithValues',
                '$path'           => $prefix . 'InheritedWithValues',
                '$expectedValues' => [
                    $prefix . 'InheritedWithValues\BaseEntity'  => [
                        'base',
                        [
                            'base'  => $prefix . 'InheritedWithValues\BaseEntity',
                            'child' => $prefix . 'InheritedWithValues\ChildEntity'
                        ]

                    ],
                    $prefix . 'InheritedWithValues\ChildEntity' => [
                        'child',
                        [
                            'base'  => $prefix . 'InheritedWithValues\BaseEntity',
                            'child' => $prefix . 'InheritedWithValues\ChildEntity'
                        ]
                    ]
                ]
            ],
            'inherited entities, should not break things with MS in the middle of hierarchy' => [
                '$namespace'      => $dirPath . 'InheritedWithMSInTheMiddle',
                '$path'           => $prefix . 'InheritedWithMSInTheMiddle',
                '$expectedValues' => [
                    $prefix . 'InheritedWithMSInTheMiddle\BaseEntity'          => [
                        'base',
                        [
                            'base'  => $prefix . 'InheritedWithMSInTheMiddle\BaseEntity',
                            'child' => $prefix . 'InheritedWithMSInTheMiddle\ChildEntity'
                        ]

                    ],
                    $prefix . 'InheritedWithMSInTheMiddle\ChildEntity'         => [
                        'child',
                        [
                            'base'  => $prefix . 'InheritedWithMSInTheMiddle\BaseEntity',
                            'child' => $prefix . 'InheritedWithMSInTheMiddle\ChildEntity'
                        ]
                    ],
                    $prefix . 'InheritedWithMSInTheMiddle\ExtendedChildEntity' => [
                        null,
                        [
                            'base'  => $prefix . 'InheritedWithMSInTheMiddle\BaseEntity',
                            'child' => $prefix . 'InheritedWithMSInTheMiddle\ChildEntity'
                        ]
                    ]
                ]
            ],
            'inherited entities, should raise duplicate exception'                           => [
                '$namespace'         => $dirPath . 'InheritedWithValuesDuplicate',
                '$path'              => $prefix . 'InheritedWithValuesDuplicate',
                '$expectedValues'    => [
                    $prefix . 'InheritedWithValuesDuplicate\BaseEntity' => [null, null]
                ],
                '$expectedException' => 'Doctrine\ORM\Mapping\MappingException'
            ],
        ];
    }

    /**
     * @param object $builder
     *
     * @return ServiceLink
     */
    private function prepareEnumBuilder($builder)
    {
        $enumBuilderServiceID = 'oro_entity_extend.link.entity_metadata_builder';
        $container            = new Container();
        $container->set($enumBuilderServiceID, $builder);

        return new ServiceLink($container, $enumBuilderServiceID);
    }
}