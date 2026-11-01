# Simple Cookie Consent

Corner cookie banner for the client WordPress sites. Replaces CookieYes.

- One PHP file, one JS file, one CSS file. ~9KB total, no build step, no dependency, no third-party request.
- Accept / Decline in a corner popup. Choice remembered for 30 days (configurable 1-365).
- Decline **deletes** the tracking cookies already on the device (`_ga`, `_gid`, `_gat`, `_gcl*`, `_fbp`, `_fbc`, `__utm*`, `_hj*`, `IDE`, `test_cookie`, `NID`, `__gads`, `__gpi`). Login, cart and session cookies are never touched.
- Google Consent Mode v2: everything denied by default in `<head>`, granted on Accept. Keeps AdSense and GA4 legal without loading anything of Google's itself.
- Styled entirely with CSS variables on `.scc`, so a theme overrides it in three lines.
- `uninstall.php` deletes its single option row. Nothing is left behind.
- Prints a "Cookie settings" link in the footer so consent can be withdrawn as easily as it was given. Switchable off when the theme has its own.

## Install

1. `bash tools/build.sh` - writes `simple-cookie-consent.zip`.
2. wp-admin → Plugins → Add New → Upload Plugin → the zip → Activate.
3. Settings → Cookie Consent. Set the message, the buttons, the corner and the privacy policy URL.

## Removing CookieYes first

Order matters: deactivate and **delete** CookieYes (deleting is what drops its options and its `cookieyes-*` cookies), then activate this. Both active at once means two banners.

CookieYes leaves `cookieyesID` and `cky-consent` in visitors' browsers. They expire on their own; this plugin does not delete another vendor's cookies.

## Styling

```css
.scc {
	--scc-bg: #14161A;
	--scc-fg: #F1F1EF;
	--scc-accent: #E3A008;
	--scc-accent-fg: #14161A;
	--scc-radius: 2px;
	--scc-width: 360px;
}
```

Full variable list at the top of `assets/consent.css`. Classes: `.scc`, `.scc__text`, `.scc__link`, `.scc__actions`, `.scc__btn`, `.scc__btn--accept`, `.scc__btn--decline`, plus `.scc--bottom-right` / `--bottom-left` / `--top-right` / `--top-left`.

## Reopening the banner

For the "Cookie settings" link a privacy policy needs:

```html
<a href="#cookie-settings">Cookie settings</a>
```

The plugin prints one in the footer by default (Settings → Cookie Consent → Footer link). Any element with class `scc-open` works too, or call `window.sccOpen()`. `window.sccConsent()` returns `allow`, `deny` or `''`.

## Page caching

The banner is built in JavaScript and the consent state is read from the cookie in the browser, so LiteSpeed, Cloudflare or any other full-page cache cannot serve one visitor's banner state to everybody. Nothing needs excluding from the cache.

## Checks

```
php tools/check.php   # options, escaping, nonce, head snippet, assets on disk
node tools/check.js   # banner behaviour, cookie write, Decline wipe, Consent Mode
```

Both run offline against stubs. No WordPress and no browser needed.

## Known ceilings

- No cookie scanner and no per-category toggles. Two buttons, one decision. Add categories only if a site genuinely sets cookies a visitor should be able to split apart.
- No consent log. The visitor's own cookie is the record, which is what the ICO expects for a site this size, but it is not an audit trail.
- The wipe list is a fixed pattern. A tag that sets a cookie under another name survives Decline until the name is added to `kill` in `assets/consent.js`.
- Scripts are not blocked before consent - Consent Mode tells Google's tags to hold off, which covers GA4 and AdSense. A hard-coded third-party embed (a YouTube iframe, a chat widget) still loads.

## License

GPL-2.0-or-later, like WordPress itself. See [LICENSE](LICENSE).
