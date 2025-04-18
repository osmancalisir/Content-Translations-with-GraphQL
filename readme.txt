=== Content Translations ===
Contributors: osmancalisir
Tags: translations, multilingual, graphql, content
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add structured content translations for posts/pages with GraphQL support and language-specific URLs.

== Description ==

A robust translation solution that provides:

* Multi-language content management for posts/pages
* WPGraphQL integration with structured translations
* Language-specific URL routing
* Content formatting options (RAW/RENDERED)
* Easy-to-use translation editor interface

Key Features:
- Tabbed translation interface in WordPress editor
- Automatic URL routing (e.g., /de/about/)
- GraphQL schema with translations object
- Content formatting options mirroring WordPress core
- Customizable language configurations
- SEO-friendly translated content handling

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-content-translations` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure languages using the `wctp_languages` filter if needed
4. Visit Settings > Permalinks to flush rewrite rules

== Using the plugin with WPGraphQL ==
This plugin allows you to fetch the translated content using GraphQL. Here is an example usage of it:
```
query MyQuery {
  page/post(id: "page/post_id") {
    content
    translations {
      de {
        content
      }
      es {
        content
      }
      fr {
        content
      }
    }
  }
}
```