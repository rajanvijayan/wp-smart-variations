# SwatchCraft 3.0: variation swatches for WooCommerce

The plugin keeps the WordPress.org slug `wp-smart-variations` and its main file `install.php`. It is released as **SwatchCraft – Variation Swatches for WooCommerce**. Everything is free.

## Where we start

### Live release 1.0 on WordPress.org
- Last updated January 2018, "tested up to" 4.1, about 10 active installs.
- One file, `install.php` (63 lines), for **WP eCommerce** only: inline jQuery printed in `wp_footer` turns `.wpsc_variation_forms` dropdowns into radio buttons, and a `<link>` tag in `wp_head` loads `css/custom.css`.
- No settings and no stored data.
- **Problems:** inline script and stylesheet, unescaped output built in JavaScript, no WooCommerce support, WP eCommerce is effectively abandoned.

## Goals
- **Shoppers:** pick colours, patterns and sizes by clicking swatches instead of reading dropdowns; see at a glance what is unavailable or out of stock; see the images of the variation they choose.
- **Shops:** colour, image, button and radio swatches for every attribute, on product pages and product cards, plus variation image galleries and a variations table with bulk add to cart.
- **Themes:** works with classic and block themes, keeps WooCommerce's own variation form working underneath, restyled through CSS custom properties.
- **Upgrade safety:** sites still on WP eCommerce keep their radio buttons.
- **Engineering:** WordPress coding standards, PHP 7.4 to 8.4, WordPress 6.5+, WooCommerce 8.0+, accessible, Plugin Check clean, the same release tooling as the other PressCraft plugins.

## Architecture
- **PHP:** namespace `SwatchCraft`, Composer classmap autoload, bootstrap stays `install.php`; text domain `wp-smart-variations`; prefixes `swatchcraft_` / `SWATCHCRAFT_`.
- **JavaScript:** `@wordpress/scripts`; entries for the front end (swatches, shop cards, gallery, table), the admin (term fields, product tab, settings app) and the block editor. Plain JavaScript on the front end; it listens to WooCommerce's `variations_form` events, which still use jQuery.
- **Data:**
  - settings in one option, `swatchcraft_settings`
  - attribute type through WooCommerce's attribute type selector; extra attribute settings in `swatchcraft_attributes`
  - term meta `swatchcraft_color`, `swatchcraft_color_2`, `swatchcraft_image`, `swatchcraft_label`
  - product meta `_swatchcraft` (per-product overrides) and variation meta `_swatchcraft_gallery`
- **Rendering:** the `woocommerce_dropdown_variation_attribute_options_html` filter adds the swatches after each select, which stays in the page (hidden). Choosing a swatch sets the select and triggers `change`, so price, stock, image and add to cart are WooCommerce's own.
- **REST API `swatchcraft/v1`:** `POST /cart` adds several variations to the cart at once (variations table).

## Features
- **Swatch types:** colour (one or two colours), image, button (label), radio, dropdown. "Auto" turns attributes without a type into buttons, including custom product attributes.
- **Product page:** selected value next to the attribute name, tooltips, clear link, unavailable combinations crossed out or hidden, out-of-stock variations crossed out, clickable or hidden; works for products with many variations (WooCommerce's AJAX lookup). Keyboard and screen reader support.
- **Product cards:** swatches of one attribute on shop cards (classic templates and WooCommerce blocks); a click shows that variation's image and links to the product with it selected.
- **Variation galleries:** extra images for each variation, shown in the product gallery when the variation is chosen.
- **Variations table:** every variation with image, attributes, price, stock and quantity, and one button to add them all to the cart. Block and shortcode.
- **Per-product settings:** a Swatches tab in the product editor to override types, colours and images.
- **Admin:** settings app (shapes, sizes, behaviour of unavailable and out-of-stock options, tooltips, product cards, table, galleries), colour and image fields on attribute terms.

## Upgrade from 1.0
1.0 stored nothing. When WP eCommerce is active, its variation dropdowns keep turning into radio buttons, now from an enqueued script.

## Milestones
| # | Scope |
|---|---|
| M1 | Foundations: bootstrap, settings, swatch data, attribute and term fields, WP eCommerce compatibility, tests with WooCommerce, release tooling |
| M2 | Product page swatches, availability, accessibility, product card swatches, styles |
| M3 | Variation galleries, variations table with bulk add to cart, price and stock in tooltips, product Swatches tab |
| M4 | Settings app, logo, banner, screenshots, `readme.txt` and `README.md`, Plugin Check, release 3.0.0 |

**Release process, as for the other plugins:** work on `release/3.0`, one PR to `master` with CI green; the owner pushes the `3.0.0` tag, the deploy finishes, then merge.

## Verification
- **PHPUnit** with WooCommerce loaded: swatch data resolution, rendering per type, availability data, galleries, bulk add to cart, settings sanitising, WP eCommerce compatibility.
- **Browser:** a WooCommerce shop on a block theme and a classic theme; colour, image and size attributes; unavailable and out-of-stock combinations; a product with many variations; product cards; galleries; variations table; keyboard only; phone width.
- **Release checks:** Plugin Check clean, shipped-PHP lint on 7.4 to 8.4, PHPUnit on WordPress 6.5 and latest.
