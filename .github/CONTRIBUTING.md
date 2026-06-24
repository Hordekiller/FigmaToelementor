# Contributing to Figma to Elementor

Thank you for considering contributing! **Figma to Elementor** is a community-driven project, and we welcome contributions of all forms — code, documentation, bug reports, and feature requests.

---

## Development Model

This project follows a **centralized development model**:

> **All development, forks, and derivative works MUST be contributed back to this repository.**
>
> We do not allow separate distributions, rebranded versions, or standalone forks.
> If you want to extend the plugin, do it here — submit a Pull Request.

This ensures:
- Every user benefits from every improvement
- No fragmentation of the codebase
- Consistent quality and security
- Proper attribution for all contributors

---

## How to Contribute

### 1. Find or Create an Issue

- Check [existing issues](https://github.com/Hordekiller/FigmaToelementor/issues)
- If your idea is new, [open an issue](https://github.com/Hordekiller/FigmaToelementor/issues/new) first to discuss

### 2. Fork & Branch

```bash
git clone https://github.com/Hordekiller/FigmaToelementor.git
cd FigmaToelementor
git checkout -b feature/your-feature-name
```

### 3. Make Changes

- Follow the coding standards (see below)
- Write clear, self-documenting code
- Test your changes

### 4. Commit & Push

```bash
git commit -m "feat: add your feature description"
git push origin feature/your-feature-name
```

### 5. Open a Pull Request

- Target the `main` branch
- Describe what your PR does and why
- Link to any related issues

---

## Coding Standards

| Language | Standard |
|---|---|
| PHP | [PSR-12](https://www.php-fig.org/psr/psr-12/) + [WordPress PHP](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) |
| JavaScript | ES6+ |
| HTML | Semantic HTML5 |
| CSS | BEM naming |

### PHP

- Namespace: `HelloFigma\*`
- File naming: `class-{name}.php`
- Strict typing: `declare(strict_types=1);`
- No Yoda conditions

### Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add new feature
fix: correct a bug
refactor: restructure code
docs: update documentation
style: formatting changes
perf: performance improvement
```

---

## Code of Conduct

- Be respectful and inclusive
- Focus on what's best for the community
- Accept constructive criticism gracefully

---

## Questions?

Open a [Discussion](https://github.com/Hordekiller/FigmaToelementor/discussions) or reach out via Issues.

**All contributors will be credited in the project README.**
