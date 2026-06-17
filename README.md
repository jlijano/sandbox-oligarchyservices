# Oligarchy Services

Static site foundation for `jlijano/sandbox-oligarchyservices`.

## Current architecture

- Static-only HTML, CSS, and JavaScript.
- No backend framework.
- No API routes.
- No database.
- No build step required.
- Analytics loader exists, but analytics are disabled by default.

## Analytics approach

The project is prepared for privacy-friendly analytics, with Plausible-style loading as the first supported provider. Tracking is intentionally disabled in `index.html` and `privacy.html` until a real provider/domain decision is made.

To enable Plausible:

1. Create the site in Plausible or a compatible self-hosted Plausible instance.
2. Confirm the public domain that should be tracked.
3. Update `window.OLIGARCHY_ANALYTICS` in each HTML page:

```html
window.OLIGARCHY_ANALYTICS = {
  enabled: true,
  provider: "plausible",
  domain: "example.com",
  respectDoNotTrack: true
};
```

If using a self-hosted script, add `scriptUrl`.

```html
scriptUrl: "https://analytics.example.com/js/script.js"
```

## Privacy and consent

Before analytics is enabled, review:

- whether analytics is cookie-free and aggregate-only;
- whether the site needs a consent banner for visitors in specific jurisdictions;
- whether IP addresses are anonymized or discarded by the provider;
- data retention settings;
- whether referrer, browser, device type, and approximate location are necessary;
- whether the privacy page accurately names the analytics provider.

Avoid fingerprinting, precise location tracking, advertising identifiers, and collecting unnecessary personal data.

## What can be tracked when analytics is enabled

With a privacy-friendly analytics provider, the site can typically report:

- visitor count and page views;
- device category;
- browser and operating system;
- referrer;
- approximate country or region.

The exact fields depend on the provider and hosting platform. This static site does not store analytics data itself.

## Local preview

Open `index.html` directly in a browser, or run a static server:

```sh
python3 -m http.server 8000
```

Then visit `http://localhost:8000`.
