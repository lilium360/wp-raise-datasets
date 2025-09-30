# Raise Datasets Marketplace

Raise Datasets Marketplace is a WordPress plugin that adds the `[raise_datasets]` shortcode. It lets you embed the public marketplace datasets hosted on the [RAISE Science](https://portal.raise-science.eu/dataset-marketplace) platform directly WordPress websites, complete with search, pagination, and links back to the canonical dataset records.

## Features
- Fetches live dataset information from the RAISE marketplace API and exposes it through a WordPress REST endpoint (`/wp-json/raise/v1/datasets`).
- Responsive, accessible listing with client-side search controls, pagination, and status messaging handled in vanilla JavaScript.
- Automatic normalization of dataset fields (title, description, organization) and direct links to each dataset detail page on the RAISE portal.
- Lightweight caching layer using WordPress transients to minimise repeated calls to the remote API.
- Translation ready: all user-facing strings are wrapped with WordPress internationalization helpers.

## Requirements
- WordPress 6.0 or newer.
- PHP 7.4 or newer with `curl`/`wp_remote_get` support.
- Network access from your WordPress host to `https://api.portal.raise-science.eu`.

## Installation
1. Download or clone this repository into your WordPress installation under `wp-content/plugins/raise-datasets-marketplace`.
2. Alternatively, create a ZIP archive of the repository and upload it from **Plugins → Add New → Upload Plugin** within the WordPress dashboard.
3. Activate **Raise Datasets Marketplace** from the **Plugins** screen.

## Usage
Insert the shortcode into any post, page, or block that accepts shortcodes:

```
[raise_datasets]
```

### Optional Parameters
- `per_page` — Number of datasets to display on each page (default `50`, minimum `1`, maximum `50`). Example: `[raise_datasets per_page="20"]`.

Once the shortcode renders, visitors can search the dataset catalogue, paginate through the results, and open the official dataset detail pages in a new tab.

## How It Works
1. The frontend JavaScript (`assets/js/raise-datasets-frontend.js`) initializes each shortcode container, handles search submissions, pagination, and renders dataset cards.
2. Each interaction calls the plugin's REST proxy (`/wp-json/raise/v1/datasets`) which, in turn, requests data from the official RAISE marketplace API endpoint: `https://api.portal.raise-science.eu/dataset/marketplace`.
3. Query parameters map as follows:
   - `search` → `searchQuery`
   - `page` → translated into `skip = (page - 1) * per_page`
   - `per_page` → `take`
4. The response is normalized, cached for five minutes, and sent back to the browser together with pagination hints (`has_more`).
5. Each dataset card includes a **View details** button that links to `https://portal.raise-science.eu/dataset-marketplace/{id}` using the dataset ID provided by the API.

## License
This plugin is licensed under the [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
