# Handoff: YOUNGSTAR Life — Product Detail Page (Laravel)

## Overview
A redesigned product detail page for youngstar.life: the header plus the product section (gallery → info → variation → cart/order → order form → description/policy), ending just above the Customer Reviews section. It supports three product types, **simple**, **variable** and **combo**, plus a new **Add to Cart** flow with a slide-in cart drawer.

**Target stack:** the existing Laravel app (Blade + whatever JS it already uses: Alpine/Livewire/jQuery/Vue). **Instruction to Claude Code:** first inspect the codebase. Reuse any feature that already exists (products, variations, cart, orders, delivery areas, auth). **If a feature is missing, build it** using the backend spec below.

## About the Design Files
`Product Section.dc.html` is a **design reference built in HTML**. It is a working prototype that shows the intended look and behavior; it is not production code. Recreate it in Blade views and components that follow the app's existing patterns. Open the file in a browser. The "ডেমো প্রোডাক্ট টাইপ" switcher at the top is only for the demo and must NOT ship; in production the type comes from `product.type`.

## Fidelity
**High-fidelity.** Match the colors, type, spacing, radii and copy exactly. All Bengali copy is final.

---

## Screens / Sections (top → bottom)

### 1. Top strip
- White bg, 1px bottom border `#e4e4e0`. Centered row, gap 18px, 13px text `#3f423f`, padding 6px 20px.
- Items: WhatsApp link (green `#25D366` icon) · "Call Us +88 01613-046803" (phone icon).

### 2. Main header (sticky, top:0, z 30)
- bg `#545754`, height 60px, shadow `0 2px 8px rgba(0,0,0,.12)`, inner max-width 1200px, padding 0 20px, flex, gap 12px.
- Logo: a 34px white circle with the logo (use the real logo asset) + "YOUNGSTAR Life" (17px, 600, white).
- Nav (flex:1, `justify-content: safe flex-end`, `overflow-x:auto`, hidden scrollbar): Home, All Products, Combo Offer, Denim 6 Pocket, Denim 4 Pocket, Cargo, Kids. Each link is 15px white, padding 8px 10px, radius 6px, hover bg `rgba(255,255,255,.12)`. Load them from the existing category menu if there is one.
- Right side, in this order: **Cart button**, then **User/Auth icon** (always last).
  - Both are 42px circles, bg `rgba(255,255,255,.12)`, white 20px stroke icon.
  - Cart badge: absolute top/right -2px, bg `#c92a2a`, min 19px, 11px bold white, 2px border `#545754`. It shows the total quantity in the cart. Clicking the button opens the cart drawer.
  - Auth icon: guest → `/login`; logged in → `/account` (or a dropdown: My Orders, Logout).

### 3. Breadcrumb
13px `#7a7d7a`: Home / Product / {product title}. Margin-bottom 16px.

### 4. Two-column layout
`grid-template-columns: repeat(auto-fit, minmax(min(100%,440px),1fr))`, gap 32px, max-width 1200px. Stacks to one column on mobile.

#### 4a. Gallery (left, sticky top 76px)
- Main image: aspect 4/5, radius 14px. Prev/next buttons are 40px white circles at 90% opacity.
- Badges: top-left discount `-X%` (bg `#c92a2a`, white, 14px bold, pill), shown only when there is a discount. Top-right, combo only: "{N} পিস কম্বো" with N as a Bengali numeral (bg `#262826`).
- Thumbnails: a 4-column grid, gap 10, radius 10, square. The active thumbnail has a 2px border `#262826`.

#### 4b. Info column (right; a vertical stack with gap 20)
1. **Title**: H1, 30px, 700, line-height 1.25.
2. **Price row**: current price `৳ 1299` (32px, 700). Old price struck through (18px `#8a8d8a`). Pill "৳ X সাশ্রয়" (bg `#fdecec`, color `#c92a2a`). The old price and pill appear only if `old_price > price`.
3. **Trust row**, 14px `#4a4d4a`: green dot + "স্টকে আছে" · "ক্যাশ অন ডেলিভারি" · "৩ দিনে এক্সচেঞ্জ". Use "স্টক শেষ" when the product is out of stock.
4. **WhatsApp note**: bg `#eaf7ef`, color `#1d5e36`, radius 12, padding 12px 14px. Text: "**বিদ্রঃ** (red) যে কোন কালার দিয়ে কম্বো করতে **WhatsApp** করুন". Links to `https://wa.me/8801613046803`.
5. **Variation card** (white, 1px `#e4e4e0`, radius 14, padding 18, gap 16). Its content depends on the type; see below.
6. **Order form card**; see below.
7. **"পণ্যের বিবরণ"** card: always open, not collapsible.
8. **"রিটার্ন ও এক্সচেঞ্জ পলিসি"**: a `<details>` accordion, closed by default.

---

## Product Types — Variation Card

### Simple
- No size picker. Only quantity, then the buttons.
- The description shows the line "ফ্রি সাইজ / একটি ভেরিয়েশন — সাইজ বাছাই করার প্রয়োজন নেই।"

### Variable
- Header row: "সাইজ · {selected}" + a "সাইজ চার্ট" link (underlined) that toggles the size chart table inline.
- Size chips: min-width 56, height 46, radius 10, 16px 600.
  - Default: bg #fff, border 1.5px `#d6d6d1`.
  - Selected: bg/border `#262826`, text white.
  - Out of stock: bg `#f4f4f2`, text `#b0b2b0`, line-through, disabled.
- Sizes and stock come from the product's variations. The price can differ per variation; if it does, update the price when a size is chosen.

### Combo (2–4 items; usually 2)
- Header: "প্রতিটি আইটেমের সাইজ বাছাই করুন" + "সাইজ চার্ট" link.
- Checkbox "সবগুলো একই সাইজ" (18px, accent `#262826`). When it is checked, picking a size for any item applies it to all items.
- One row per combo item: bg `#f7f7f5`, radius 12, padding 12. Grid `96px | 1fr`, gap 14.
  - **Item image** (important): 96px wide, aspect 3/4, radius 10. 2px border in the item's color swatch. A bottom label bar (bg `#262826`, white 11px) shows the color name.
  - Pill "আইটেম ১" (12px 600, bg `#ecece8`) + color name + right-aligned "সাইজ 32" or "সাইজ বাছাই করুন".
  - **Item product name** (important, 16px 600). A combo can contain ANY product, so always show its name.
  - Size chips: 50×42, radius 9. Same states as above, with stock checked per the item's product.
- Title/badge: the prototype generates the title from the colors ("Beige + Off-White Combo Pack (2 pcs)"). In production use the admin-entered title, falling back to that generated one.
- Qty label becomes "কম্বো সেট" (number of combo sets).

### Size chart (shared)
Columns: Size, Waist, Length, F-Hip, F-Rise, Leg Open, Thigh. Selected size rows are highlighted with bg `#f4f1e8`. Footnote "সব মাপ ইঞ্চিতে". The data is per product (or per size-chart template) and can be edited by an admin.

### Qty + buttons (all types)
- Qty stepper: 46×44 −/+ buttons, value 48px wide, bordered 1.5px `#d6d6d1`, radius 10. Min 1. Max = available stock.
- Two equal buttons, height 52, radius 10:
  - **কার্টে যোগ করুন**: outline 1.5px `#262826`, cart icon, hover bg `#f4f4f2`.
  - **এখনই অর্ডার করুন**: filled `#262826`, white 700, hover `#3f423f`. Smooth-scrolls to the order form.
- Validation before either action: variable needs a size, and combo needs every item sized. Otherwise show a red `#c92a2a` 14px message: "সাইজ বাছাই করুন" / "প্রতিটি আইটেমের সাইজ বাছাই করুন".

---

## Order Form (Quick Order / COD, no login required)
- Title "অর্ডার ফর্ম" (20px 700), sub "পণ্য হাতে পেয়ে টাকা পরিশোধ করুন".
- Fields, each 46px tall, 1.5px `#d6d6d1` border, radius 10, focus border `#262826`:
  - "আপনার নাম" (placeholder "পুরো নাম লিখুন")
  - "মোবাইল নাম্বার" ("01XXXXXXXXX", numeric)
  - "পূর্ণ ঠিকানা" (textarea, "বাসা/রোড, এলাকা, থানা, জেলা")
- **Delivery area**: radio cards in a grid `auto-fill minmax(170px,1fr)`, gap 8. Each card shows the label + "৳fee"; the selected card gets a `#262826` border, `#f7f7f5` bg and a filled dot. Defaults (from DB/admin):
  - ঢাকার ভিতরে ৳80
  - ঢাকার বাইরে ৳140 (default)
  - গাজীপুর ৳110
  - নারায়ণগঞ্জ ৳110
  - কেরানীগঞ্জ ৳110
- Summary box (bg `#f7f7f5`): "পণ্য/কম্বো × qty → subtotal", "ডেলিভারি চার্জ → fee", then a dashed divider and "মোট → total" (18px 700).
- Submit: "অর্ডার কনফার্ম করুন · ৳ {total}" (54px tall, filled).
- Validation: the name is required; the phone must match `^01\d{9}$`; the address is required; the variation must be complete. Error text: "নাম, সঠিক ১১ ডিজিটের মোবাইল নাম্বার ও ঠিকানা লিখুন".
- Success state replaces the form: a green check circle (bg `#eaf7ef`), "অর্ডার সম্পন্ন হয়েছে", "আমাদের প্রতিনিধি শীঘ্রই {phone} নাম্বারে কল করবেন।", and a "আবার অর্ডার করুন" button. Optionally redirect to a thank-you page instead.

## Cart Drawer
- Overlay `rgba(20,20,20,.4)`. Panel on the right, `width:min(400px,100%)`, white, shadow `-8px 0 30px rgba(0,0,0,.12)`.
- Header "আপনার কার্ট ({count})" + × close.
- Right after an add: green notice "✓ কার্টে যোগ হয়েছে".
- Each item is a grid `64px | 1fr | auto`: thumbnail 64×76, title (14px 600), variant text (e.g. "সাইজ 32", or for a combo "6 Pocket Semi Baggy Cargo Pant (Beige, 32) + …"), "পরিমাণ: n", line total, and a red "মুছুন" remove link.
- Empty state: "কার্ট খালি".
- Footer: "সাবটোটাল ৳X", note "ডেলিভারি চার্জ চেকআউটে যোগ হবে", buttons **চেকআউট করুন** (filled, goes to `/checkout`) and **কেনাকাটা চালিয়ে যান** (outline, closes the drawer).

## Description & Policy
- **পণ্যের বিবরণ** (always open):
  - Combo: first a list "এই কম্বোতে যা থাকছে", one line per item with a swatch dot + **product name** · color.
  - Simple: the free-size line above.
  - Then a ✓ (green `#2f9e44`) feature list taken from the product description (one bullet per line).
- **রিটার্ন ও এক্সচেঞ্জ পলিসি** (closed accordion), text from settings:
  - সারা বাংলাদেশে ক্যাশ অন ডেলিভারি এভেইলেবল।
  - আপনি যদি আপনার ক্রয়কৃত প্রোডাক্ট নিয়ে সন্তুষ্ট না হন, তবে শুধু ডেলিভারি চার্জ প্রদান করে ডেলিভারি ম্যানের কাছে সহজেই ফেরত দিতে পারবেন।
  - আমাদের আছে ডেলিভারির পর ৩ দিন পর্যন্ত এক্সচেঞ্জ সুবিধা।

---

## Backend Spec (build anything that doesn't exist)

### Suggested schema
```
products: id, name, slug, type ENUM('simple','variable','combo'), price, old_price NULL,
          description TEXT, features JSON NULL, size_chart_id NULL, stock INT NULL (simple),
          is_active, timestamps
product_images: id, product_id, path, sort_order
product_variants: id, product_id, size, color NULL, sku, price NULL (override), stock INT
combo_items: id, combo_product_id, item_product_id, color_label, color_hex, image_path NULL, sort_order
             -- 2..4 rows per combo; each item's sizes/stock come from item_product's variants
size_charts: id, name, columns JSON, rows JSON
delivery_areas: id, name_bn, fee, sort_order, is_default, is_active
carts: id, user_id NULL, session_id, timestamps
cart_items: id, cart_id, product_id, variant_id NULL, combo_selection JSON NULL, qty, unit_price
orders: id, user_id NULL, name, phone, address, delivery_area_id, delivery_fee,
        subtotal, total, status ENUM('pending','confirmed','shipped','delivered','returned','cancelled'),
        payment_method DEFAULT 'cod', source ENUM('quick','cart'), timestamps
order_items: id, order_id, product_id, variant_id NULL, combo_selection JSON NULL,
             title_snapshot, variant_text, qty, unit_price, line_total
```
`combo_selection` example: `[{"combo_item_id":1,"variant_id":12,"size":"32"},{"combo_item_id":2,"variant_id":19,"size":"32"}]`

### Routes
```
GET  /product/{slug}              ProductController@show
POST /cart/add                    CartController@add      {product_id, variant_id?, combo_selection?, qty}
GET  /cart                        CartController@index    (JSON for drawer)
DELETE /cart/{item}               CartController@remove
PATCH  /cart/{item}               CartController@update   {qty}
GET  /checkout                    CheckoutController@show (same form as Order Form, prefilled from cart)
POST /order                       OrderController@store   quick order from product page OR from cart
GET  /login, /register, /account  (Laravel Breeze/Fortify if no auth exists)
```

### Rules
- Guest carts are keyed by session; merge them into the user's cart on login.
- Recalculate prices and delivery fees on the server; never trust client totals.
- Check stock on add-to-cart and on order. For a combo, check every item's variant.
- Phone validation: `regex:/^01\d{9}$/`.
- A quick order creates an order with a single item. A cart checkout creates an order from all cart items and then clears the cart.
- Show the cart count in the header on every page (via a view composer).
- Admin, if no panel exists: CRUD for products (type selector; combo item picker for 2–4 products with color label/hex/image), variants with stock, size charts, delivery areas, the policy text, and an order list with status updates.

## State (frontend)
`selectedImage`, `selectedSize` (variable), `comboSizes[]` + `sameSize` (combo), `qty`, `chartOpen`, `deliveryAreaId`, `name/phone/address`, `error`, `formError`, `placed`, `cart[]`, `drawerOpen`, `justAdded`. Alpine.js is a good fit if the app has no JS framework.

## Design Tokens
- Ink `#262826` · Ink-hover `#3f423f` · Header `#545754`
- Text secondary `#4a4d4a` / `#6b6e6b` / `#7a7d7a` · Disabled `#b0b2b0`
- Border `#e4e4e0` · Input border `#d6d6d1` · Page bg `#f4f4f2` · Soft panel `#f7f7f5` · Pill `#ecece8`
- Danger/discount `#c92a2a` / bg `#fdecec` · Success `#2f9e44` · WhatsApp `#25D366`, bg `#eaf7ef`, text `#1d5e36`
- Chart highlight `#f4f1e8`
- Font: **Hind Siliguri** (Google Fonts) 400/500/600/700
- Type: H1 30/700 · price 32/700 · section 17–20/600–700 · body 15 · small 13–14
- Radii: 6 (nav), 9–10 (chips, inputs, buttons), 12 (combo rows, notes), 14 (cards, gallery), pill 20
- Spacing: card padding 18, column gap 32, stack gap 20/16/12

## Assets
The product images in the prototype are striped placeholders; use the real product, combo item and logo images. The icons are simple inline SVGs (cart, user, phone, WhatsApp); use the app's icon set if it has one.

## Files
- `Product Section.dc.html`: the full interactive prototype (open it directly in a browser).
