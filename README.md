# plg_system_astroidghsvs

- Don't use it if you don't need it. Needs some skills and background knowledge.
- Helper plugin for ghsvs.de for Astroid Framework (since 2.4.7) templates.
- And other templates that want to use the on-the-fly feature to compile SCSS via scssphp/scssphp library.
- Or if you want to use the included library [scssphp/scssphp](https://github.com/scssphp/scssphp) elsewhere and load it early.


1) Compile SCSS with the library "scssphp/scssphp" included in this plugin instead of the library packed with Astroid Framework (this library was not up-to-date enough for my needs and is updated too rarely)..

2) Create .css and .min.css files. Create sourcemaps.

3) Helps to compile SCSS independently of the Astroid framework. Separate SCSS folders, separate SCSS structure. However, the template index.php code must be prepared accordingly (special overrides and so on).

4) Can be used for other templates as well, just to have the feature of having SCSSPhp available in latest version. But also for on-the-fly compilation. The template index.php code must be prepared accordingly (special overrides and so on).

-----------------------------------------------------

# My personal build procedure (WSL 1, Debian, Win 10)
- Prepare/adapt `./package.json`.
- `cd /mnt/z/git-kram/plg_system_astroidghsvs`

## node/npm updates/installation
- `npm run g-npm-update-check` or (faster) `ncu`
- `npm run g-ncu-override-json` (if needed) or (faster) `ncu -u`
- `npm install` (if needed)

## Composer updates/installation
- Check/adapt versions in `/src/composer.json`. Something to bump in `vendor/`?

```
cd _composer/

composer outdated

OR

composer show -l
```
- both commands accept the parameter `--direct` to show only direct dependencies in the listing

### Automatically "download" PHP packages into `/src/vendor/`

```
cd src/
composer install
```

OR
(whenever libraries in vendor/ shall be updated)

```
cd src/
composer update
```

### Automatically "download" JS/CSS packages into `/node_modules`

- I you want to check first: `npm run g-npm-update-check`

- If you want to adapt package.json automatically first: `npm run g-ncu-override-json`


- `cd ..`
- `npm install`

OR

- `npm update`

### Build new Joomla package ZIP.

- <strike>May be necessary: `nvm use 12` or `nvm use 13` to get rid of f'ing messages of NodeJs 14+ that nobody understands but the Node creators and JS professors.</strike>

- `node build.js`

#####
- New, installable ZIP is in `/dist/` afterwards.

- FYI: Packed files for this ZIP can be seen in `/package/`.

#### For Joomla update server
- Create new release with new tag.
- Get download link for new `dist/plg_blahaba_blubber...zip` **inside new tag branch** and add to release description and update the update XML.
