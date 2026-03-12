---
name: localization-manager
description: Manage Laravel localization and language files across modules. Use when identifying untranslated text, creating or updating lang files, or ensuring consistency in translation keys and styles.
---

# Localization Manager

This skill focuses on maintaining a high standard for Laravel application localization, specifically for projects using modular architectures (like `app-modules`).

## Responsibilities

### 1. Translation Identification
- Scan code for "hardcoded" strings that should be localized.
- Distinguish between internal strings (e.g., config keys, database fields) and user-facing strings.
- Identify missing keys for existing localizations.

### 2. Standardized File Structure
- **Location**: Use module-specific paths: `app-modules/{module}/resources/lang/{locale}/{file}.php`.
- **Header**: Always include `<?php` followed by `declare(strict_types=1);`.
- **Format**: Return a flat or nested PHP array.
- **Styling**:
    - Use `snake_case` for keys.
    - End sentences with a period (unless it's a short label or title).
    - Use comments to group related keys within the file.
    - Use `:placeholder` for dynamic content.

### 3. Namespace Management
- Correctly use module namespaces: `__('{module}::{file}.{key}')`.
- For shared translations, use `__('shared::{file}.{key}')` or the default `__('{file}.{key}')`.

### 4. Semantic Key Naming
- Propose keys that are descriptive and clear (e.g., `user_not_found` instead of `error_1`).
- Avoid generic keys like `error`, `success`, `message` without context.

## Workflow

1.  **Analyze**: Find untranslated strings in the requested scope.
2.  **Verify**: Check if a similar key already exists in the module or shared files.
3.  **Propose**: Suggest keys and translations, following the "period at the end" rule for sentences.
4.  **Implement**: Update or create the relevant `.php` file in the correct module.
5.  **Refactor**: Replace the hardcoded strings in the code with the localization helper (`__()`).

## Efficiency
- Use `grep_search` to quickly find strings like `'text'` or `"text"` in views, controllers, and services.
- Skip scanning `vendor`, `storage`, and `node_modules` directories.
