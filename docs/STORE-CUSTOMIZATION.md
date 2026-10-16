# Build different types of stores

Universal Commerce is an adaptable **single-store deployment**, not a hosted multi-tenant SaaS product. A fresh installation contains department and attribute definitions but does not install commercial products or guarantee a ready-to-sell theme for every industry.

## Common setup flow

1. Sign in as a store owner; open **Admin → Store Settings / Storefront Configuration**.
2. Set your store's name, contact information, primary language and currency.
3. Define or review departments, categories and brand assignments.
4. Add products and SKU variants using the universal catalog editor; specify prices and inventory for each relevant variant.
5. Configure navigation, homepage sections and product discoverability; publish your changes where the editor requires it.
6. Configure shipping areas, delivery fees, accepted manual payment options, returns and customer-service information.
7. Test the exact checkout, permissions, stock, order, return and notification scenarios you expect customers to use.

## Suggested departments and options

| Type | Category examples | Variant / product attributes to verify |
| --- | --- | --- |
| **Clothing & footwear** | Shirts, jeans, dresses, sneakers | Size, color, fit, materials; stock per size/color SKU |
| **Electronics** | Phones, headphones, peripherals | Storage, color, model, specifications, warranty |
| **Cosmetics & beauty** | Makeup, skincare, haircare | Shade, type, size, skin compatibility where applicable |
| **Perfumes** | Men, women, unisex | Fragrance family, concentration, volume |
| **General retail** | Home, gifts, supplies | Flexible attributes, categories and stock |

The current SQL migrations include `fashion`, `beauty`, `electronics`, `perfumes` and `general` **department definitions**. Definitions alone are not proof that every customer-facing filter and theme works: verify the UI for your chosen department.

## Compatibility details

- The legacy `perfumes.php` route, older table names and `PERFUME_*` environment variables are retained so upgrading merchants do not break their data.
- The generic variant/catalog system is implemented in source but must be validated end-to-end before live orders.
- No licensed or scraped product feeds are included as a ready-to-use shop catalog.
- This project currently supports **cash on delivery and manual bank transfer/InstaPay request flows**, not a live card/payment gateway.

For incomplete features and community tasks, see [../ROADMAP.md](../ROADMAP.md).
