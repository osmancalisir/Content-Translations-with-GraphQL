# MultiTab Translator

Adds language translation tabs to WordPress posts/pages with GraphQL support.

## Installation
1. Upload folder to `/wp-content/plugins/`
2. Activate plugin

## GraphQL Query
```graphql
query GetTranslations($id: ID!) {
  post(id: $id) {
    title
    translations {
      en
      de
      fr
      es
      # ...other languages
    }
  }
}