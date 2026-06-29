---
name: 📬 Pull Request
description: Submit a contribution
labels: []
body:
  - type: markdown
    attributes:
      value: |
        Thanks for contributing! Please fill out this form to help us review your PR.

  - type: dropdown
    id: type
    attributes:
      label: PR type
      options:
        - feat — new feature
        - fix — bug fix
        - refactor — code restructure
        - docs — documentation
        - style — formatting / code style
        - perf — performance improvement
        - test — adding/updating tests
        - ci — CI configuration
    validations:
      required: true

  - type: textarea
    id: summary
    attributes:
      label: Summary
      description: What does this PR do? Why is it needed?
      placeholder: Clear and concise description of the change.
    validations:
      required: true

  - type: textarea
    id: related_issues
    attributes:
      label: Related issues
      description: Link any related issues (e.g. "Closes #123").
      placeholder: Closes #...

  - type: textarea
    id: testing
    attributes:
      label: How was this tested?
      description: Describe the testing you performed.
      placeholder: |
        - Ran PHPCS: `phpcs --standard=phpcs.xml`
        - Ran PHPStan: `phpstan analyse --configuration=phpstan.neon --no-progress`
        - Added/updated unit tests in tests/*.php
        - Tested on WordPress 6.7 + Elementor 3.27
    validations:
      required: true

  - type: checkboxes
    id: checks
    attributes:
      label: Checklist
      options:
        - label: PHPCS passes (`phpcs --standard=phpcs.xml`)
          required: true
        - label: PHPStan passes at Level 6 (`phpstan analyse --configuration=phpstan.neon --no-progress`)
          required: true
        - label: Existing tests pass (`phpunit`)
          required: true
        - label: New functionality includes tests
        - label: Commit messages follow Conventional Commits
          required: true
        - label: Code follows PSR-12 + WordPress PHP coding standards
          required: true
        - label: README / docs updated (if applicable)
