DC PS Omnibus – Golden Master Edition
-------------------------------------

Description
-----------
DC PS Omnibus is a lightweight, file-based price history module for PrestaShop 1.7, 8 and 9. 
It provides a fully compliant implementation of the EU Omnibus Directive without using SQL tables, 
overrides or performance-heavy logic. The module works instantly after installation — zero configuration, 
zero database usage, zero risk.

Main Features
-------------
- 100% Omnibus-compliant price history tracking
- Flat-file architecture (no SQL, no custom tables)
- Separate history file for each product and combination
- Automatic cleanup of entries older than 30 days
- Prevents duplicate logs within the same request
- Compatible with Specific Prices and combination prices
- Works on product pages only (after_price hook)
- Secure data storage protected by .htaccess
- Fully compatible with PrestaShop 1.7, 8 and 9
- Multistore friendly – isolated logs per shop
- No settings panel required – install and it works

Installation
------------
1. Download the ZIP package from Design Cart LAB or GitHub.
2. Do NOT unpack the ZIP.
3. In the PrestaShop admin panel, go to:
   Modules > Module Manager > Upload a module
4. Upload the ZIP file.
5. The module installs automatically and is ready to use.

How It Works
------------
- On every price change (product update, combination update, Specific Price actions),
  the module logs the current price with a timestamp.
- History is stored in /modules/dc_ps_omnibus/history/
- Only records from the last 30 days are kept.
- The lowest price from this period is displayed automatically on the product page.

Compatibility
-------------
- PrestaShop 1.7.x
- PrestaShop 8.x
- PrestaShop 9.x
- Custom themes (no overrides, no TPL modifications)
- Multistore
- Caching systems (Smarty, Redis, LiteSpeed Cache, Varnish)

Support
-------
For updates, documentation and support, visit:
https://www.designcart.pl/laboratorium.html

Author
------
Design Cart
www.designcart.pl
