# Secret Drawer 🤫

A hidden drawer in wp-admin, unlocked by a secret word. Silly to find,
genuinely useful to have.

> **Status:** in early development (0.1.0). See [PLAN.md](PLAN.md) for the
> full implementation plan and roadmap.

Type the secret word on any wp-admin page and a drawer slides in from the
edge: your notes, your quick links, site notifications — whatever you've
tucked away. Nobody else sees it. Nobody else knows it's there.

## Screenshots

![The launcher drawer, tucked against the right edge.](.wordpress.org/screenshot-1.png)
![A Notes cubby popped out beside it, with an open editor.](.wordpress.org/screenshot-2.png)
![Drawer settings: secret word, roles, position, and the Cubby Library.](.wordpress.org/screenshot-3.png)
![The bottom-sheet variant, with cubby panels rising from its edge.](.wordpress.org/screenshot-4.png)

## Requirements

- WordPress 6.4+
- PHP 7.4+

## Development

No build step: plain PHP, plain JS (`wp-components` via
`wp.element.createElement`), one CSS file. No npm.

```bash
# Lint all PHP
find . -name '*.php' ! -path './vendor/*' -exec php -l {} \;
```

## Extending

Other plugins can add their own cubbies and levers with one filter —
see [SECRET-DRAWER-EXTENDING.md](SECRET-DRAWER-EXTENDING.md).

## License

GPL-2.0-or-later