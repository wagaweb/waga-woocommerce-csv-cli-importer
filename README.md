# Waga WooCommerce CSV Cli Importer

This project is in a early alpha stage.

The aim is to develop a cli tool that can read a CSV file and can alter all sort of WooCommerce product data (prices, stock quantities, meta data, ect..)

## Getting started

- Import the project in your theme in a custom folder (eg: 'cli').
- Run a `composer install` inside the project folder.
- Require che `command.php` file: `<?php require_once 'cli/waga-woocommerce-csv-cli-importer/src/command.php';` in your `functions.php` file.
- Now you should be able to call `wp wwc-prod-csv-import`.

## How to import data

The script can import data from a CSV file. This file must have one product per row and a series of column with the data to update. The CSV headers (the first line) can be the default provided in the file `docs/standard_headers.md` or can be customized through a _manifest file_.

With a CSV (product-list.csv) formatted like that:

| SKU                  | meta:_regular_price | meta:_sale_price |
|----------------------|---------------------|------------------|
| woo-hoodie-red       | 45                  | 42               |
| woo-hoodie-blue-logo | 45                  |                  |
| woo-hoodie-green     | 45                  | 38               |
| woo-hoodie-blue      | 45                  |                  |

You can run: `wp --allow-root wwc-prod-csv-import /path/to/product-list.csv` to mass-update the listed products.

If you want (or forced to) use custom headers, like: 

| Sku                  | Price               | Sale Price       | 
|----------------------|---------------------|------------------|
| woo-hoodie-red       | 45                  | 42               |
| woo-hoodie-blue-logo | 45                  |                  |
| woo-hoodie-green     | 45                  | 38               |
| woo-hoodie-blue      | 45                  |                  |

You can create _manifest file_, eg: `prices-import.json`, like that:

```
{
  "Price": "meta:_regular_price",
  "Sale Price": "meta:_sale_price",
  "Sku": "SKU",
  "_types": {
    "Price": "price",
    "Sale Price": "price"
  }
}
```

To map the headers to the default counterparts.

The `_types` field tells the script how to treat the data in the specified column. For now, only `price` is supported. `price` type will cast the data to float e store it in a optimal way for WooCommerce.
