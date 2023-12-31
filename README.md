# NOT CHILD TEMPLATES READY!

# plg_system_astroidghsvs

- Don't use it if you don't need it. _**Needs some skills and background knowledge.**_
- Helper plugin for ghsvs.de for Astroid Framework (since 2.4.7) templates.
- And other templates that want to use the on-the-fly feature to compile SCSS via scssphp/scssphp library.
- Or if you want to use the included library [scssphp/scssphp](https://github.com/scssphp/scssphp) elsewhere and load it early.

1) Compile SCSS with the library "scssphp/scssphp" included in this plugin instead of the library packed with Astroid Framework (this library was not up-to-date enough for my needs and is updated too rarely)..

2) Create .css and .min.css files. Create sourcemaps. Create .min.css.gz files.

3) Helps to compile SCSS independently of the Astroid framework. Separate SCSS folders, separate SCSS structure. However, the template index.php code must be prepared accordingly (special overrides and so on).

4) Can be used for other templates as well, just to have the feature of having SCSSPhp available in latest version. But also for on-the-fly compilation. The template index.php code must be prepared accordingly (special overrides and so on).

-----------------------------------------------------

# My personal build procedure (WSL 1 or 2, Debian, Win 10)

**@since versions greater than 2022.05.16_1.10.3.0: Build procedure uses local repo fork of https://github.com/GHSVS-de/buildKramGhsvs**

- Prepare/adapt `./package.json`.
- `cd /mnt/z/git-kram/plg_system_astroidghsvs`

## node/npm updates/installation
If not done yet:
- `npm install` (if needed)
### Update
- `npm run updateCheck` or (faster) `npm outdated`
- `npm run update` (if needed) or (faster) `npm update --save-dev`

## Composer updates/installation
- Check/adapt versions in `./_composer/composer.json`. Something to bump in `vendor/`?

```
cd _composer/

composer outdated

OR

composer show -l
```
- both commands accept the parameter `--direct` to show only direct dependencies in the listing

### Automatically "download" PHP packages into `./_composer/vendor/`

```
cd _composer/
composer install
```

OR
(whenever libraries in vendor/ shall be updated)

```
cd _composer/
composer update
```

## Build installable ZIP package
- `cd ..` if still in `/_composer/`.
- `node build.js`
- New, installable ZIP is in `./dist` afterwards.
- All packed files for this ZIP can be seen in `./package`. **But only if you disable deletion of this folder at the end of `build.js`**.

### For Joomla update and changelog server
- Create new release with new tag.
  - See and copy and complete release description in `dist/release_no-chengelog.txt`.
- Extracts(!) of the update XML for update servers are in `./dist` as well.
