<?php

declare(strict_types=1);

namespace Pim\Bundle\EnrichBundle\Normalizer;

use Pim\Bundle\EnrichBundle\Provider\Form\FormProviderInterface;
use Pim\Bundle\VersioningBundle\Manager\VersionManager;
use Pim\Component\Catalog\FamilyVariant\EntityWithFamilyVariantAttributesProvider;
use Pim\Component\Catalog\Localization\Localizer\AttributeConverterInterface;
use Pim\Component\Catalog\Model\EntityWithFamilyVariantInterface;
use Pim\Component\Catalog\Model\ProductModelInterface;
use Pim\Component\Catalog\Model\ValueInterface;
use Pim\Component\Catalog\Model\VariantAttributeSetInterface;
use Pim\Component\Catalog\Model\VariantProductInterface;
use Pim\Component\Catalog\Repository\LocaleRepositoryInterface;
use Pim\Component\Catalog\Repository\ProductModelRepositoryInterface;
use Pim\Component\Catalog\ValuesFiller\EntityWithFamilyValuesFillerInterface;
use Pim\Component\Enrich\Converter\ConverterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\scalar;

/**
 * @author    Adrien Pétremann <adrien.petremann@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class ProductModelNormalizer implements NormalizerInterface
{
    /** @var string[] */
    private $supportedFormat = ['internal_api'];

    /** @var NormalizerInterface */
    private $normalizer;

    /** @var NormalizerInterface */
    private $versionNormalizer;

    /** @var NormalizerInterface */
    private $fileNormalizer;

    /** @var VersionManager */
    private $versionManager;

    /** @var AttributeConverterInterface */
    private $localizedConverter;

    /** @var ConverterInterface */
    private $productValueConverter;

    /** @var FormProviderInterface */
    private $formProvider;

    /** @var LocaleRepositoryInterface */
    private $localeRepository;

    /** @var EntityWithFamilyValuesFillerInterface */
    private $entityValuesFiller;

    /** @var EntityWithFamilyVariantAttributesProvider */
    private $attributesProvider;

    /** @var ProductModelRepositoryInterface */
    private $productModelRepository;

    /**
     * @param NormalizerInterface                       $normalizer
     * @param NormalizerInterface                       $versionNormalizer
     * @param NormalizerInterface                       $fileNormalizer
     * @param VersionManager                            $versionManager
     * @param AttributeConverterInterface               $localizedConverter
     * @param ConverterInterface                        $productValueConverter
     * @param FormProviderInterface                     $formProvider
     * @param LocaleRepositoryInterface                 $localeRepository
     * @param EntityWithFamilyValuesFillerInterface     $entityValuesFiller
     * @param EntityWithFamilyVariantAttributesProvider $attributesProvider
     * @param ProductModelRepositoryInterface           $productModelRepository
     */
    public function __construct(
        NormalizerInterface $normalizer,
        NormalizerInterface $versionNormalizer,
        NormalizerInterface $fileNormalizer,
        VersionManager $versionManager,
        AttributeConverterInterface $localizedConverter,
        ConverterInterface $productValueConverter,
        FormProviderInterface $formProvider,
        LocaleRepositoryInterface $localeRepository,
        EntityWithFamilyValuesFillerInterface $entityValuesFiller,
        EntityWithFamilyVariantAttributesProvider $attributesProvider,
        ProductModelRepositoryInterface $productModelRepository
    ) {
        $this->normalizer            = $normalizer;
        $this->versionNormalizer     = $versionNormalizer;
        $this->fileNormalizer        = $fileNormalizer;
        $this->versionManager        = $versionManager;
        $this->localizedConverter    = $localizedConverter;
        $this->productValueConverter = $productValueConverter;
        $this->formProvider          = $formProvider;
        $this->localeRepository      = $localeRepository;
        $this->entityValuesFiller    = $entityValuesFiller;
        $this->attributesProvider    = $attributesProvider;
        $this->productModelRepository = $productModelRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($productModel, $format = null, array $context = []): array
    {
        $this->entityValuesFiller->fillMissingValues($productModel);

        $normalizedProductModel = $this->normalizer->normalize($productModel, 'standard', $context);
        $normalizedProductModel['values'] = $this->localizedConverter->convertToLocalizedFormats(
            $normalizedProductModel['values'],
            $context
        );

        $normalizedProductModel['family'] = $productModel->getFamilyVariant()->getFamily()->getCode();
        $normalizedProductModel['values'] = $this->productValueConverter->convert($normalizedProductModel['values']);

        $oldestLog = $this->versionManager->getOldestLogEntry($productModel);
        $newestLog = $this->versionManager->getNewestLogEntry($productModel);

        $created = null !== $oldestLog ? $this->versionNormalizer->normalize($oldestLog, 'internal_api') : null;
        $updated = null !== $newestLog ? $this->versionNormalizer->normalize($newestLog, 'internal_api') : null;

        $levelAttributes = [];
        foreach ($this->attributesProvider->getAttributes($productModel) as $attribute) {
            $levelAttributes[] = $attribute->getCode();
        }

        $axesAttributes = [];
        foreach ($this->attributesProvider->getAxes($productModel) as $attribute) {
            $axesAttributes[] = $attribute->getCode();
        }

        $normalizedFamilyVariant = $this->normalizer->normalize($productModel->getFamilyVariant(), 'standard');

        $normalizedProductModel['meta'] = [
                'family_variant'            => $normalizedFamilyVariant,
                'form'                      => $this->formProvider->getForm($productModel),
                'id'                        => $productModel->getId(),
                'created'                   => $created,
                'updated'                   => $updated,
                'model_type'                => 'product_model',
                'attributes_for_this_level' => $levelAttributes,
                'attributes_axes'           => $axesAttributes,
                'image'                     => $this->normalizeImage($productModel->getImage(), $format, $context),
                'navigation'                => $this->normalizeNavigation($productModel, $format, $context),
            ] + $this->getLabels($productModel);

        return $normalizedProductModel;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof ProductModelInterface && in_array($format, $this->supportedFormat);
    }

    /**
     * @param ProductModelInterface $productModel
     *
     * @return array
     */
    private function getLabels(ProductModelInterface $productModel): array
    {
        $labels = [];

        foreach ($this->localeRepository->getActivatedLocaleCodes() as $localeCode) {
            $labels[$localeCode] = $productModel->getLabel($localeCode);
        }

        return ['label' => $labels];
    }

    /**
     * @param ValueInterface|null $data
     * @param string              $format
     * @param array               $context
     *
     * @return array|null
     */
    private function normalizeImage(?ValueInterface $data, string $format, array $context = []): ?array
    {
        if (null === $data || null === $data->getData()) {
            return null;
        }

        return $this->fileNormalizer->normalize($data->getData(), $format, $context);
    }

    /**
     * Normalize navigation information of the given $productModel.
     *
     * @param ProductModelInterface $productModel
     * @param string                $format
     * @param array                 $context
     *
     * @return array
     */
    private function normalizeNavigation(
        ProductModelInterface $productModel,
        string $format,
        array $context = []
    ): array {
        $parent = $productModel->getParent();
        $navigationData = [
            'own'             => $this->getNavigationInformation($productModel, $format, $context),
            'parent'          => null,
            'parent_siblings' => [],
            'siblings'        => [],
            'children'        => [],
        ];

        if (null === $parent) {
            $children = $this->productModelRepository->findChildrenProductModels($productModel);
        } else {
            $children = $this->productModelRepository->findChildrenProducts($productModel);
        }

        foreach ($children as $child) {
            $navigationData['children'][] = $this->getNavigationInformation($child, $format, $context);
        }

        if (null === $parent) {
            return $navigationData;
        }

        $siblings = $this->productModelRepository->findSiblingsProductModels($productModel);
        foreach ($siblings as $sibling) {
            $navigationData['siblings'][] = $this->getNavigationInformation($sibling, $format, $context);
        }

        $parentSiblings = $this->productModelRepository->findSiblingsProductModels($parent);
        foreach ($parentSiblings as $parentSibling) {
            $navigationData['parent_siblings'][] = $this->getNavigationInformation($parentSibling, $format, $context);
        }

        $navigationData['parent'] = $this->getNavigationInformation($parent, $format, $context);

        return $navigationData;
    }

    /**
     * Returns the navigation information needed by the PEF for the given $entity.
     * For example:
     * [
     *     'axes_values' => '[blue], [xl]',
     *     'label'       => 'Neon Tshirt Blue XL',
     *     'image'       => '/img/tshirt_blue.jpg'
     * ]
     *
     * @param EntityWithFamilyVariantInterface $entity
     * @param string                           $format
     * @param array                            $context
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    private function getNavigationInformation(
        EntityWithFamilyVariantInterface $entity,
        string $format,
        array $context = []
    ): array {
        if (!$entity instanceof ProductModelInterface && !$entity instanceof VariantProductInterface) {
            throw new \InvalidArgumentException(sprintf(
                '"%s" or "%s" expected, "%s" received',
                ProductModelInterface::class,
                VariantProductInterface::class,
                get_class($entity)
            ));
        }

        $axesValues = [];
        foreach ($this->attributesProvider->getAxes($entity) as $axisAttribute) {
            $axesValues[] = (string) $entity->getValue($axisAttribute->getCode());
        }

        return [
            'axes_values' => implode(', ', $axesValues),
            'label'       => $entity->getLabel(),
            'image'       => $this->normalizeImage($entity->getImage(), $format, $context)
        ];
    }
}
