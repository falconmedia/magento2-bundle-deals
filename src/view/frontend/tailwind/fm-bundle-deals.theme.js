/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

/**
 * Tailwind theme extension used by the BundleDeals templates (`*-fm-*` utilities).
 * Every colour reads a CSS custom property with a neutral fallback, so the module looks fine out of the box and
 * your theme can restyle it by setting the `--fmbd-*` variables (see fm-bundle-deals.css).
 *
 * In your theme's tailwind.config.js:
 *
 *   const fmBundleDeals = require('../../../../../../../vendor/falconmedia/magento2-bundle-deals/src/view/frontend/tailwind/fm-bundle-deals.theme.js');
 *   module.exports = { theme: { extend: { ...fmBundleDeals } }, ... };
 *
 * (adjust the relative path to where your theme sits; a path through app/code works too).
 */
const c = (name, fallback) => `var(--fmbd-${name}, ${fallback})`;

module.exports = {
    colors: {
        fmbd: {
            bg: c('bg', '#ffffff'),
            surface: { 1: c('surface-1', '#ffffff'), 2: c('surface-2', '#f5f5f3'), 3: c('surface-3', '#e9e9e6') },
            text: { DEFAULT: c('text', '#16181d'), muted: c('text-muted', '#55595f'), soft: c('text-soft', '#3a3e45') },
            border: { DEFAULT: c('border', '#d4d4d0'), strong: c('border-strong', '#8a8d93') },
            primary: c('primary', '#1d4ed8'),
            'on-primary': c('on-primary', '#ffffff'),
            focus: c('focus', '#1d4ed8'),
            cta: c('cta', '#0a7d0e'),
            'on-cta': c('on-cta', '#ffffff'),
            ok: { DEFAULT: c('ok', '#177032'), bg: c('ok-bg', '#e4f4e8') },
            warning: { DEFAULT: c('warning', '#8a5a00'), bg: c('warning-bg', '#fff4d6') },
            error: { DEFAULT: c('error', '#b42318'), bg: c('error-bg', '#fdecea') },
            rating: c('rating', '#b25e00'),
            discount: { DEFAULT: c('discount', '#c2410c'), bg: c('discount-bg', '#ffedd5') },
            usp: { bg: c('usp-bg', '#16181d'), text: c('usp-text', '#f2f3f5') },
            'disabled-bg': c('disabled-bg', '#ecece9'),
            'disabled-text': c('disabled-text', '#8c9097'),
        },
    },
    fontSize: {
        'fmbd-xs': ['0.8rem', { lineHeight: '1.2' }],
        'fmbd-sm': ['0.875rem', { lineHeight: '1.2' }],
        'fmbd-base': ['1rem', { lineHeight: '1.5' }],
        'fmbd-lg': ['1.25rem', { lineHeight: '1.25' }],
        'fmbd-xl': ['1.563rem', { lineHeight: '1.2' }],
        'fmbd-2xl': ['1.953rem', { lineHeight: '1.2' }],
    },
    spacing: {
        'fmbd-1': '0.25rem', 'fmbd-2': '0.375rem', 'fmbd-3': '0.5625rem', 'fmbd-4': '0.84375rem',
        'fmbd-5': '1.265625rem', 'fmbd-6': '1.8984375rem', 'fmbd-7': '2.84765625rem', 'fmbd-8': '4.5rem',
    },
    borderRadius: { 'fmbd-sm': '4px', 'fmbd-md': '8px', 'fmbd-lg': '12px' },
};
