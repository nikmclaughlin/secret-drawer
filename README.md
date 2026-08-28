# Secret Drawer 🤫

A hidden drawer for your wp-admin. Only those with secret knowledge can access its riches. Who knows what's inside?

> **Status:** in early development (0.1.0). See [PLAN.md](PLAN.md) for the
> full implementation plan and roadmap.

Type the secret word on any wp-admin page and the secret drawer appears! Fill it with your notes, links, busts of Socrates, or your own custom cubbies. Nobody else sees it. Nobody else knows it's there.

## Try it in WordPress Playground

**[▶ Launch the live demo](https://playground.wordpress.net/?plugin=https%3A%2F%2Fgithub.com%2Fnikmclaughlin%2Fsecret-drawer%2Freleases%2Flatest%2Fdownload%2Fsecret-drawer.zip)**

The demo installs the latest release into a throwaway WordPress site.
When it loads, type the default secret word **`hellodolly`** anywhere on
an admin screen (outside a text field) and the drawer will slide in. 🤫

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
