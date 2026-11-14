# vytux_cousins

[![CI](https://github.com/vytux-com/vytux_cousins/actions/workflows/ci.yml/badge.svg)](https://github.com/vytux-com/vytux_cousins/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/vytux/vytux_cousins)](https://packagist.org/packages/vytux/vytux_cousins)
[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

A [webtrees](https://www.webtrees.net) custom module that adds a **Cousins** tab to the individual page, listing the cousins of the person you are viewing.

Originally based on the `simpl_cousins` module by Nigel Osborne (kiwtrees.net), ported to webtrees by Vytautas Krivickas ([vytux.com](https://vytux.com)).

## Features

- Lists first cousins, grouped by their parents' family, split into **Father's family** and **Mother's family**.
- Optional **half cousins**: children of a parent's half-siblings (that is, cousins who share only one grandparent). Tick *Include half cousins* at the top of the tab. Half cousins are labelled in the list, and the choice is remembered for your browser session.
- Optional **distant cousins**: choose *Cousin degree* at the top of the tab to include second, third, fourth and fifth cousins. Each is labelled by degree, and the choice is remembered for your browser session.
- A cousin related through both parents is listed and counted on both sides, but only once in the overall total.
- Respects webtrees privacy settings.
- Translated into Czech, Chinese (Simplified and Traditional), Danish, Dutch, Finnish, French, German, Hebrew, Lithuanian, Norwegian (Bokmål and Nynorsk), Russian and Swedish. Strings without a translation fall back to English.

## Requirements

- webtrees 2.2 or later
- PHP 8.3 or later

## Installation

### With Composer (recommended)

From your webtrees root folder:

```
composer require vytux/vytux_cousins
```

The module is installed into `modules_v4/vytux_cousins`.

### Manually

Download the latest release, unzip it and upload the folder to `modules_v4/vytux_cousins/` on your web server.

### Enable the tab

1. Sign in to webtrees as an administrator and go to **Control panel → Modules → All modules**. Find **Cousins** and make sure it is enabled.
2. Go to **Control panel → Modules → Tabs**. Set the access level and position for the Cousins tab. By default it may be shown to managers only.
3. Click **Save**.

## Upgrading

With Composer run `composer update vytux/vytux_cousins`. Otherwise replace the module folder with the files from the latest release.

## Adding more cousin types

Cousin types are defined in `cousinTypes()` in `src/VytuxCousinsTabModule.php`. Each type describes which relatives of a parent count as the starting point. Types marked `optional` automatically get a check-box on the tab. This makes it straightforward to add further types, for example second cousins.

## Translations

Translations live in `src/VytuxCousinsTabModule.php`, one method per language. Pull requests adding or improving a language are welcome.

## Support

Please report bugs and request features on the [issue tracker](https://github.com/vytux-com/vytux_cousins/issues). You can also email [webtrees@vytux.com](mailto:webtrees@vytux.com), or ask general webtrees questions on the [webtrees forum](https://www.webtrees.net/index.php/forum).

## Licence

Copyright © 2013 Vytautas Krivickas and vytux.com. Based on simpl_cousins, copyright © 2013 Nigel Osborne and kiwtrees.net.

Released under the GNU General Public License, version 2 or (at your option) any later version. See the licence header in the source files.
