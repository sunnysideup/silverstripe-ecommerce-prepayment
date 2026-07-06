# Silverstripe CMS 6 Upgrade Guide

This document outlines the changes required to upgrade your project to be compatible with Silverstripe CMS 6.

## Configuration (`composer.json`)

### ⚠️ BREAKING CHANGE: Core Dependency Update

The project now requires Silverstripe Framework version 6.

*   **Action Required:** Update your `composer.json` to require `silverstripe/framework` version `^6.0`.

    ```diff
    - "silverstripe/framework": "~4.0 || ~5.0"
    + "silverstripe/framework": "^6.0"
    ```

### New Requirements

*   **Composer Installers**: The `composer/installers` plugin is now required. Ensure it is included in the `allow-plugins` section of your `composer.json`.

    ```json
    "allow-plugins": {
        "silverstripe/vendor-plugin": true,
        "composer/installers": true
    }
    ```

*   **Audit Configuration**: A new `audit` configuration has been added to `composer.json` to avoid blocking insecure packages.

    ```json
    "audit": {
        "block-insecure": false
    }
    ```
