# Sameday Courier for OpenCart

Shipping extension for **OpenCart 2.3**, **3.x**, and **4.x**. It connects your store to [Sameday](https://www.sameday.ro/) so customers can choose Sameday at checkout, and you can create and manage AWBs from the admin.

**Current version:** 1.8.4

---

## Features

### Setup & shipping

- Configure Sameday as a shipping method (credentials, testing/live, host country RO / BG / HU)
- Import and refresh **services**, **pickup points**, and **lockers / easyBox**
- Enable estimated shipping cost at checkout
- Show the interactive easyBox map on checkout
- Domestic and cross-border services (home delivery and locker / PUDO)



### AWB management

- Generate AWB for a single order (from the order page)
- Add parcels to an existing AWB
- Download AWB as PDF
- View AWB history and status summary
- Delete AWB



### Bulk actions (Sales → Orders)

- Bulk **generate** AWBs
- Bulk **remove** AWBs
- Clear bulk error feedback
- Currency mismatch warnings when order currency differs from the destination-country currency (RO→RON, BG→EUR, HU→HUF), with a confirmation before proceeding



### Order status

- Optional setting: change order status when an AWB is generated
- Previous status is stored and restored when the AWB is removed
- Choose **Do not change** to leave the order status untouched



### Currencies

AWB and estimate requests send the **destination-country** currency expected by Sameday:

- Romania → **RON**
- Bulgaria → **EUR**
- Hungary → **HUF**

---



## Account & pricing

Using the plugin is free. Sameday delivery itself is contract-based. After you sign a contract you receive a username and password for the API (demo and/or production).

Support / feedback: [plugineasybox@sameday.ro](mailto:plugineasybox@sameday.ro)

---



## Install (store owners)



### OpenCart 2.3 / 3.x

1. Build or download the matching package (`sameday.2.ocmod.zip` or `sameday.3.ocmod.zip`).
2. In admin go to **Extensions → Installer** and upload the zip.
3. Go to **Extensions → Modifications** and click **Refresh**.
4. Go to **Extensions → Extensions → Shipping**, find **Sameday**, and click **Install**, then **Edit**.
5. Enter your Sameday username and password, choose testing or live, select host country, and save / log in.
6. Refresh **services**, **pickup points**, and **lockers**. Set a **default pickup point**.
7. Enable the shipping method and configure services you want to show at checkout.



### OpenCart 4.x

1. Build or download `sameday.ocmod.zip`.
2. Install it via the OpenCart 4 extension installer.
3. Enable and configure **Sameday** under shipping extensions (same credential / import steps as above).

After setup, Sameday options appear at checkout. From **Sales → Orders** you can generate AWBs one-by-one or in bulk.

---



## Build packages (developers)

Each tree (`opencart-23`, `opencart-30`, `opencart-40`) includes the same `build.sh`. You can build **any** target version from **any** tree; when sources match, the zip is byte-identical.

```bash
# From the root of any OC tree (opencart-23, opencart-30, or opencart-40):
./build.sh 2   # → sameday.2.ocmod.zip   (OpenCart 2.3)
./build.sh 3   # → sameday.3.ocmod.zip   (OpenCart 3.x)
./build.sh 4   # → sameday.ocmod.zip     (OpenCart 4.x)
```


| Argument | OpenCart | Output file           |
| -------- | -------- | --------------------- |
| `2`      | 2.3      | `sameday.2.ocmod.zip` |
| `3`      | 3.x      | `sameday.3.ocmod.zip` |
| `4`      | 4.x      | `sameday.ocmod.zip`   |


---


## Code style

This project uses [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) with rules in `phpcs.xml.dist`.

```bash
# Check
php vendor/bin/phpcs --standard=phpcs.xml.dist

# Auto-fix where possible
php vendor/bin/phpcbf --standard=phpcs.xml.dist
```

---



## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.