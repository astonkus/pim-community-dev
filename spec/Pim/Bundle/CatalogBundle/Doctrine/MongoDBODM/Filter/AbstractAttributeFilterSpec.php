<?php

namespace spec\Pim\Bundle\CatalogBundle\Doctrine\MongoDBODM\Filter;

use PhpSpec\ObjectBehavior;
use Pim\Bundle\CatalogBundle\Doctrine\MongoDBODM\Filter\AbstractAttributeFilter;
use Pim\Component\Catalog\Exception\InvalidArgumentException;
use Pim\Component\Catalog\Model\AttributeInterface;
use Pim\Bundle\CatalogBundle\Validator\AttributeValidatorHelper;
use Prophecy\Argument;

class AbstractAttributeFilterSpec extends ObjectBehavior
{
    function let(AttributeValidatorHelper $attrValidatorHelper)
    {
        $this->beAnInstanceOf('spec\Pim\Bundle\CatalogBundle\Doctrine\MongoDBODM\Filter\ConcreteAttributeFilter');
        $this->beConstructedWith($attrValidatorHelper);
    }

    function it_is_a_filter()
    {
        $this->shouldHaveType('Pim\Bundle\CatalogBundle\Query\Filter\AttributeFilterInterface');
    }

    function it_throws_an_exception_when_locale_is_expected(
        $attrValidatorHelper,
        AttributeInterface $attribute
    ) {
        $e = new \LogicException('Attribute "attributeCode" expects a locale, none given.');

        $attribute->getCode()->willReturn('attributeCode');
        $attribute->isLocalizable()->willReturn(true);
        $attrValidatorHelper->validateLocale($attribute, null)->willThrow($e);

        $this->shouldThrow(
            InvalidArgumentException::expectedFromPreviousException($e, 'attributeCode', 'filter', 'concrete')
        )->during('testLocaleAndScope', [$attribute, null, Argument::any()]);
    }

    function it_throws_an_exception_when_locale_is_not_expected(
        $attrValidatorHelper,
        AttributeInterface $attribute
    ) {
        $e = new \LogicException('Attribute "attributeCode" does not expect a locale, "en_US" given.');

        $attribute->getCode()->willReturn('attributeCode');
        $attribute->isLocalizable()->willReturn(false);
        $attrValidatorHelper->validateLocale($attribute, 'en_US')->willThrow($e);

        $this->shouldThrow(
            InvalidArgumentException::expectedFromPreviousException($e, 'attributeCode', 'filter', 'concrete')
        )->during('testLocaleAndScope', [$attribute, 'en_US', 'ecommerce']);
    }

    function it_throws_an_exception_when_locale_is_expected_but_not_activated(
        $attrValidatorHelper,
        AttributeInterface $attribute
    ) {
        $e = new \LogicException('Attribute "attributeCode" expects an existing and activated locale, "uz-UZ" given.');

        $attribute->getCode()->willReturn('attributeCode');
        $attribute->isLocalizable()->willReturn(true);
        $attrValidatorHelper->validateLocale($attribute, 'uz-UZ')->willThrow($e);

        $this->shouldThrow(
            InvalidArgumentException::expectedFromPreviousException($e, 'attributeCode', 'filter', 'concrete')
        )->during('testLocaleAndScope', [$attribute, 'uz-UZ', 'ecommerce']);
    }

    function it_throws_an_exception_when_scope_is_expected(
        $attrValidatorHelper,
        AttributeInterface $attribute
    ) {
        $e = new \LogicException('Attribute "attributeCode" expects a scope, none given.');

        $attribute->getCode()->willReturn('attributeCode');
        $attribute->isLocalizable()->willReturn(false);
        $attribute->isScopable()->willReturn(true);
        $attrValidatorHelper->validateLocale($attribute, null)->shouldBeCalled();
        $attrValidatorHelper->validateScope($attribute, null)->willThrow($e);

        $this->shouldThrow(
            InvalidArgumentException::expectedFromPreviousException($e, 'attributeCode', 'filter', 'concrete')
        )->during('testLocaleAndScope', [$attribute, null, null]);
    }

    function it_throws_an_exception_when_scope_is_not_expected(
        $attrValidatorHelper,
        AttributeInterface $attribute
    ) {
        $e = new \LogicException('Attribute "attributeCode" does not expect a scope, "ecommerce" given.');

        $attribute->getCode()->willReturn('attributeCode');
        $attribute->isLocalizable()->willReturn(false);
        $attribute->isScopable()->willReturn(false);
        $attrValidatorHelper->validateLocale($attribute, null)->shouldBeCalled();
        $attrValidatorHelper->validateScope($attribute, 'ecommerce')->willThrow($e);

        $this->shouldThrow(
            InvalidArgumentException::expectedFromPreviousException($e, 'attributeCode', 'filter', 'concrete')
        )->during('testLocaleAndScope', [$attribute, null, 'ecommerce']);
    }

    function it_throws_an_exception_when_scope_is_expected_but_not_existing(
        $attrValidatorHelper,
        AttributeInterface $attribute
    ) {
        $e = new \LogicException('Attribute "attributeCode" expects an existing scope, "ecommerce" given.');

        $attribute->getCode()->willReturn('attributeCode');
        $attribute->isLocalizable()->willReturn(false);
        $attribute->isScopable()->willReturn(true);
        $attrValidatorHelper->validateLocale($attribute, null)->shouldBeCalled();
        $attrValidatorHelper->validateScope($attribute, 'ecommerce')->willThrow($e);

        $this->shouldThrow(
            InvalidArgumentException::expectedFromPreviousException($e, 'attributeCode', 'filter', 'concrete')
        )->during('testLocaleAndScope', [$attribute, null, 'ecommerce']);
    }
}

class ConcreteAttributeFilter extends AbstractAttributeFilter
{
    function __construct(AttributeValidatorHelper $attrValidatorHelper)
    {
        $this->attrValidatorHelper = $attrValidatorHelper;
    }

    function addAttributeFilter(
        AttributeInterface $attribute,
        $operator,
        $value,
        $locale = null,
        $scope = null,
        $options = []
    ) {
        // need to be implemented
    }

    function supportsAttribute(AttributeInterface $attribute)
    {
        // need to be implemented
    }

    function testLocaleAndScope(AttributeInterface $attribute, $locale, $scope)
    {
        $this->checkLocaleAndScope($attribute, $locale, $scope, 'concrete');
    }
}
