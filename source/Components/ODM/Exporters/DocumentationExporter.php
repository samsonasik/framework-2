<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Components\ODM\Exporters;

use Spiral\Components\Files\FileManager;
use Spiral\Components\ODM\Document;
use Spiral\Components\ODM\ODM;
use Spiral\Components\ODM\SchemaBuilder;
use Spiral\Components\ODM\Schemas\CollectionSchema;
use Spiral\Components\ODM\Schemas\DocumentSchema;
use Spiral\Core\Component;
use Spiral\Support\Generators\Reactor\ClassElement;
use Spiral\Support\Generators\Reactor\FileElement;
use Spiral\Support\Generators\Reactor\NamespaceElement;

class DocumentationExporter extends Component
{
    /**
     * Namespace to use for virtual collections and compositors.
     */
    const VIRTUAL_NAMESPACE = '\\virtualClasses\\';

    /**
     * ODM documents schema.
     *
     * @var SchemaBuilder
     */
    protected $builder = null;

    /**
     * Required compositor declarations.
     *
     * @var DocumentSchema[]
     */
    protected $compositors = [];

    /**
     * Header DOC comment.
     *
     * @var string
     */
    protected $header = [
        'This file was generated by Spiral Framework. Script should never be included to application.',
        'Do not modify content of this file as it will be erased every schema update.'
    ];

    /**
     * New instance of documentation exporter. Reactor classes will be used to create such documentation.
     *
     * @param SchemaBuilder $builder
     */
    public function __construct(SchemaBuilder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * Get virtual name to use for document compositor.
     *
     * @param DocumentSchema $documentSchema DocumentSchema to get composition for.
     * @param bool           $namespace      Include virtual namespace.
     * @return null|string
     */
    protected function compositorClass(DocumentSchema $documentSchema, $namespace = true)
    {
        //First primary class with defined collection
        $primaryClass = $documentSchema->primaryClass(true);

        $name = explode('\\', $primaryClass);
        $name = end($name);

        return ($namespace ? self::VIRTUAL_NAMESPACE : '')
        . '_CMP_' . $name . '_' . substr(md5($primaryClass), 0, 5);
    }

    /**
     * Get collection class should be used to represent document and it's all children.
     *
     * @param DocumentSchema $documentSchema DocumentSchema to get collection class for.
     * @param bool           $namespace      Include virtual namespace.
     * @return null|string
     */
    protected function collectionClass(DocumentSchema $documentSchema, $namespace = true)
    {
        //First primary class with defined collection
        $primaryClass = $documentSchema->primaryClass(true);

        $name = explode('\\', $primaryClass);
        $name = end($name);

        return ($namespace ? self::VIRTUAL_NAMESPACE : '')
        . '_CL_' . $name . '_' . substr(md5($primaryClass), 0, 5);
    }

    /**
     * Get virtual documentation for Document model. Will render all model fields, methods,
     * compositions and aggregations.
     *
     * @param DocumentSchema $document
     * @return NamespaceElement
     */
    protected function renderDocument(DocumentSchema $document)
    {
        $model = new ClassElement($name = $document->getShortName());

        //This name should be used in static methods, as ODM allows to store all class children in
        //one collection
        $primaryDocument = $document->primaryDocument()->getShortName();

        //Static collection methods
        if ($document->getCollection())
        {
            $model->method(
                'find',
                [
                    '@param array $query',
                    '@return ' . $this->collectionClass($document) . '|' . $primaryDocument . '[]'
                ], ['query']
            )->setStatic(true)->parameter('query')->setOptional(true, [])->setType('array');

            $model->method(
                'findOne',
                [
                    '@param array $query',
                    '@return ' . $primaryDocument . '|' . $name
                ],
                ['query']
            )->setStatic(true)->parameter('query')->setOptional(true, [])->setType('array');

            $model->method(
                'findByID',
                [
                    '@param mixed $mongoID',
                    '@return ' . $primaryDocument . '|' . $name
                ],
                ['mongoID']
            )->setStatic(true);
        }

        //Document creation method
        $model->method(
            'create',
            [
                '@param array $fields',
                '@return ' . $name
            ],
            ['fields']
        )->setStatic(true);

        //Compositions
        foreach ($document->getCompositions() as $name => $composition)
        {
            if (!$composited = $this->builder->getDocument($composition['class']))
            {
                continue;
            }

            if ($composition['type'] == ODM::CMP_ONE)
            {
                $model->property($name, '@var \\' . $composited->getClass());
            }
            else
            {
                $compositorClass = $this->compositorClass($composited);

                $this->compositors[$composited->getClass()] = $composited;
                $model->property(
                    $name,
                    '@var \\' . $composited->getClass() . '[]|' . $compositorClass
                );
            }
        }

        //Accessors
        foreach ($document->getAccessors() as $name => $accessor)
        {
            if ($model->hasProperty($name))
            {
                continue;
            }

            if (is_array($accessor))
            {
                $accessor = $accessor[0];
            }

            $model->property($name, '@var \\' . $accessor);
        }

        foreach ($document->getFields() as $field => $type)
        {
            if ($model->hasProperty($field))
            {
                continue;
            }

            $isArray = false;
            if (is_array($type))
            {
                $type = $type[0];
                $isArray = true;
            }

            if ($type && lcfirst($type[0]) != $type[0])
            {
                //Starts with capital letter, looks like a class name
                $type = '\\' . $type;
            }

            $model->property($field, '@var ' . $type . ($isArray ? '[]' : ''));
        }

        //Aggregations
        foreach ($document->getAggregations() as $name => $aggregation)
        {
            if (!$aggregated = $this->builder->getDocument($aggregation['class']))
            {
                continue;
            }

            if ($aggregation['type'] == Document::ONE)
            {
                $model->method(
                    $name,
                    [
                        '@param array $query',
                        '@return \\' . $aggregated->getClass()
                    ],
                    ['query']
                )->parameter('query')->setOptional(true, [])->setType('array');
            }
            else
            {
                $collectionClass = $this->collectionClass($aggregated);
                $model->method(
                    $name,
                    [
                        '@param array $query',
                        '@return \\' . $aggregated->getClass() . '[]|' . $collectionClass
                    ],
                    ['query']
                )->parameter('query')->setOptional(true, [])->setType('array');
            }
        }

        return (new NamespaceElement($document->getNamespace()))->addClass($model);
    }


    /**
     * Get virtual documentation for ODMCollection, all method return values will be replaced with
     * appropriate document type.
     *
     * @param CollectionSchema $collection
     * @return ClassElement
     */
    protected function renderCollection(CollectionSchema $collection)
    {
        $name = $this->collectionClass($collection->primaryDocument(), false);

        $class = (new ClassElement($name))->cloneSchema(SchemaBuilder::COLLECTION);
        $class->removeConstant('SINGLETON');
        $class->setParent(false);

        foreach ($class->getProperties() as $property)
        {
            $class->removeProperty($property->getName());
        }

        //Replaces
        $class->replaceComments("static", $name . '|' . '\\' . $collection->primaryClass() . '[]');
        $class->replaceComments(SchemaBuilder::DOCUMENT, $collection->primaryClass());
        $class->replaceComments("Document", '\\' . $collection->primaryClass());

        return $class;
    }

    /**
     * Get virtual documentation for ODMCompositor.
     *
     * @param DocumentSchema $document
     * @return ClassElement
     */
    protected function compositor(DocumentSchema $document)
    {
        $name = $this->compositorClass($document->primaryDocument(), false);

        $class = (new ClassElement($name))->cloneSchema(SchemaBuilder::COMPOSITOR);
        $class->setParent(false)->setInterfaces([]);

        foreach ($class->getProperties() as $property)
        {
            $class->removeProperty($property->getName());
        }

        //Replaces
        $class->replaceComments("Compositor", $name);
        $class->replaceComments(SchemaBuilder::DOCUMENT, $document->primaryClass());
        $class->replaceComments("Document", '\\' . $document->primaryClass());

        return $class;
    }

    /**
     * Render virtual documentation to file. Reactor RPHPFile will be used.
     *
     * @param string $filename
     * @return bool
     */
    public function render($filename)
    {
        $phpFile = FileElement::make()->setComment($this->header);

        foreach ($this->builder->getDocumentSchemas() as $document)
        {
            if ($document->isAbstract())
            {
                continue;
            }

            $phpFile->addElement($this->renderDocument($document));
        }

        $virtualNamespace = new NamespaceElement(self::VIRTUAL_NAMESPACE);
        $virtualNamespace->setUses([
            'Spiral\Components\ODM\ODM',
            'Spiral\Support\Pagination\Paginator',
            'Spiral\Support\Pagination\PaginatorException',
            'Spiral\Components\ODM\ODMException',
            'Spiral\Components\ODM\CursorReader',
            'Psr\Http\Message\ServerRequestInterface',
            'Psr\Log\LoggerInterface',
            'Spiral\Components\Debug\Logger'
        ]);

        foreach ($this->builder->getCollections() as $collection)
        {
            $virtualNamespace->addClass($this->renderCollection($collection));
        }

        foreach ($this->compositors as $document)
        {
            $virtualNamespace->addClass($this->compositor($document));
        }

        $phpFile->addElement($virtualNamespace);

        return $phpFile->renderFile($filename, FileManager::RUNTIME, true);
    }
}