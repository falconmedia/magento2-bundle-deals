<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Ui\DataProvider\Product\Form\Modifier;

use FalconMedia\BundleDeals\Model\Config\Source\Rules;
use FalconMedia\BundleDeals\Model\Config\Source\Slots;
use FalconMedia\BundleDeals\Model\ResourceModel\Index;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Container;
use Magento\Ui\Component\Form\Element\DataType\Text;
use Magento\Ui\Component\Form\Element\Select;
use Magento\Ui\Component\Form\Field;
use Magento\Ui\Component\Form\Fieldset;

/**
 * "Bundle Deals" fieldset on the product form: manual slot membership per bundle deal slot.
 */
class BundleDeals extends AbstractModifier
{
    public const FIELDSET = 'falconmedia_bundle_deals';
    public const DATA_KEY = 'bundle_deals';
    public const ACL_RESOURCE = 'FalconMedia_BundleDeals::product_links';

    public const MODE_AUTO = 'auto';
    public const MODE_INHERIT = 'inherit';
    public const MODE_ENTRY = 'entry';
    public const MODE_NO_ENTRY = 'no_entry';

    public function __construct(
        private readonly LocatorInterface $locator,
        private readonly Slots $slots,
        private readonly Rules $rules,
        private readonly Index $index,
        private readonly UrlInterface $urlBuilder,
        private readonly Escaper $escaper,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    /**
     * Form field key for a "ruleId:slotId" slot value.
     */
    public function fieldKey(string $slotValue): string
    {
        [$ruleId, $slotId] = array_map('intval', explode(':', $slotValue));

        return sprintf('r%d_s%d', $ruleId, $slotId);
    }

    /**
     * "ruleId:slotId" for a form field key, or null when the key is not a slot field.
     */
    public static function slotValue(string $fieldKey): ?string
    {
        return preg_match('/^r(\d+)_s(\d+)$/', $fieldKey, $match) ? $match[1] . ':' . $match[2] : null;
    }

    /**
     * Adds the saved link mode of every slot to the product form data.
     *
     * @param array<int|string,mixed> $data
     * @return array<int|string, mixed>
     */
    public function modifyData(array $data)
    {
        $productId = (int) $this->locator->getProduct()->getId();
        if (!$this->isAllowed()) {
            return $data;
        }

        $links = $productId ? $this->index->getProductLinks($productId) : [];
        $values = [];
        foreach ($this->slots->toOptionArray() as $option) {
            $key = (string) $option['value'];
            $mode = self::MODE_AUTO;
            if (array_key_exists($key, $links)) {
                $mode = match ($links[$key]) {
                    true => self::MODE_ENTRY,
                    false => self::MODE_NO_ENTRY,
                    null => self::MODE_INHERIT,
                };
            }
            $values[$this->fieldKey($key)] = $mode;
        }
        $data[$productId ?: ''][self::DATA_KEY] = $values;

        return $data;
    }

    /**
     * Adds the bundle deals fieldset to the product form.
     *
     * @param array<string,mixed> $meta
     * @return array<string, mixed>
     */
    public function modifyMeta(array $meta)
    {
        if (!$this->isAllowed()) {
            return $meta;
        }

        $meta[self::FIELDSET] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('Bundle Deals'),
                        'componentType' => Fieldset::NAME,
                        'collapsible' => true,
                        'opened' => false,
                        'dataScope' => 'data.' . self::DATA_KEY,
                        'sortOrder' => $this->getNextGroupSortOrder($meta, 'related', 95),
                    ],
                ],
            ],
            'children' => $this->getChildren(),
        ];

        return $meta;
    }

    /**
     * Returns the form fields, one per bundle deal slot.
     *
     * @return array<string, mixed>
     */
    private function getChildren(): array
    {
        $options = $this->slots->toOptionArray();
        if (!$options) {
            return [
                'empty' => $this->getHtml(
                    10,
                    sprintf(
                        '%s <a href="%s">%s</a>',
                        $this->escaper->escapeHtml(__('There are no bundle deals with slots yet.')),
                        $this->escaper->escapeUrl($this->urlBuilder->getUrl('bundledeals/rule/')),
                        $this->escaper->escapeHtml(__('Manage bundle deals'))
                    )
                ),
            ];
        }

        $ruleLabels = [];
        foreach ($this->rules->toOptionArray() as $rule) {
            $ruleLabels[(int) $rule['value']] = (string) $rule['label'];
        }
        $productId = (int) $this->locator->getProduct()->getId();
        $membership = $productId ? $this->index->getMembershipForProducts([$productId]) : [];

        $children = [
            'intro' => $this->getHtml(
                0,
                $this->escaper->escapeHtml(__(
                    'Automatic: the product is in a slot when it matches the slot conditions. '
                    . 'Always include: the product is in the slot regardless of the conditions.'
                ))
            ),
        ];
        $sortOrder = 10;
        $currentRule = null;
        foreach ($options as $option) {
            $value = (string) $option['value'];
            [$ruleId, $slotId] = array_map('intval', explode(':', $value));
            if ($ruleId !== $currentRule) {
                $currentRule = $ruleId;
                $children['rule_' . $ruleId] = $this->getHtml(
                    $sortOrder++,
                    sprintf(
                        '<strong><a href="%s" target="_blank">%s</a></strong>',
                        $this->escaper->escapeUrl(
                            $this->urlBuilder->getUrl('bundledeals/rule/edit', ['rule_id' => $ruleId])
                        ),
                        $this->escaper->escapeHtml(
                            __('Edit bundle deal %1', $ruleLabels[$ruleId] ?? '#' . $ruleId)
                        )
                    )
                );
            }

            $label = (string) $option['label'];
            $prefix = ($ruleLabels[$ruleId] ?? '') . ' / ';
            if ($prefix !== ' / ' && str_starts_with($label, $prefix)) {
                $label = substr($label, strlen($prefix));
            }
            $isMember = isset($membership[$ruleId][$slotId]);

            $children[$this->fieldKey($value)] = [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'componentType' => Field::NAME,
                            'formElement' => Select::NAME,
                            'dataType' => Text::NAME,
                            'dataScope' => $this->fieldKey($value),
                            'label' => $label,
                            'options' => $this->getModeOptions(),
                            'default' => self::MODE_AUTO,
                            'notice' => $isMember
                                ? __('Currently in this slot.')
                                : __('Currently not in this slot.'),
                            'sortOrder' => $sortOrder++,
                        ],
                    ],
                ],
            ];
        }

        return $children;
    }

    /**
     * Returns the link mode options of a slot field.
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    private function getModeOptions(): array
    {
        return [
            ['value' => self::MODE_AUTO, 'label' => __('Automatic (conditions)')],
            ['value' => self::MODE_INHERIT, 'label' => __('Always include, entry as set on the slot')],
            ['value' => self::MODE_ENTRY, 'label' => __('Always include, use as entry')],
            ['value' => self::MODE_NO_ENTRY, 'label' => __('Always include, not as entry')],
        ];
    }

    /**
     * Returns the escaped HTML of a field note.
     *
     * @return array<string, mixed>
     */
    private function getHtml(int $sortOrder, string $html): array
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => Container::NAME,
                        'component' => 'Magento_Ui/js/form/components/html',
                        'additionalClasses' => 'admin__fieldset-note',
                        'content' => $html,
                        'sortOrder' => $sortOrder,
                    ],
                ],
            ],
        ];
    }

    /**
     * Tells whether the admin user may manage bundle deals.
     */
    private function isAllowed(): bool
    {
        return $this->authorization->isAllowed(self::ACL_RESOURCE);
    }
}
