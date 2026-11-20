# Frontend and UI/UX Standards

## Design system

- Establish design tokens for color, typography, spacing, radii, shadows, z-index, and breakpoints. Use tokens rather than scattered magic values.
- Use a consistent type scale and a limited set of font weights. Maintain readable line lengths and hierarchy.
- Use semantic HTML and accessible components. Prefer native controls where appropriate.
- Build reusable components for header, footer, buttons, inputs, product cards, badges, dialogs, empty states, alerts, pagination, and loading states.
- Avoid excessive shadows, gradients, animations, icon containers, and competing accent colors.
- Ensure all interactive elements have hover, focus-visible, active, disabled, and loading states.
- Use meaningful labels and accessible names for icon-only buttons.

## Storefront information architecture

Recommended information hierarchy, subject to existing product requirements:

1. Announcement bar (only when an active, accurate announcement exists).
2. Main header: logo, primary navigation, search, account, cart.
3. Optional category navigation.
4. Promotional hero or featured campaign.
5. Categories.
6. Top / featured products.
7. Daily Interest or campaign/editorial content.
8. Additional product collections and trust information.
9. Footer: company, help, policies, contact, and social links.

Do not display placeholder sections in production. If no campaign exists, hide the campaign module or show an approved fallback.

## Product card content

- Product image with consistent aspect ratio and object-fit behavior.
- Product name, current price, optional original price/discount, availability, and rating only when supported by real data.
- Add-to-cart action with clear loading/success/error feedback.
- Wishlist action only when implemented and authenticated/guest behavior is defined.
- Do not show fake sales counts, fake urgency, fake reviews, or unverified stock.
- Provide a useful image alt description; decorative images use empty alt text.

## Responsive behavior

- Mobile-first layout with tested breakpoints based on content, not device brand names.
- Header must collapse gracefully; search and cart remain discoverable.
- Product grids adapt columns without clipping or horizontal scrolling.
- Touch targets should be comfortably usable (aim for at least 44×44 CSS px).
- Test long product names, missing images, large prices, empty collections, and translated text.

## Accessibility and UX

- Keyboard access to navigation, menus, dialogs, filters, and forms.
- Visible focus indicator; do not remove outlines without replacement.
- Correct heading hierarchy and form labels.
- Color contrast meets WCAG 2.2 AA where applicable.
- Announce asynchronous status changes to assistive technology.
- Respect reduced-motion preferences.
- Provide loading skeletons only when useful; provide empty, error, and retry states.
- Forms explain validation errors next to the relevant field and in an accessible summary where needed.

## Performance

- Use responsive image sizes and modern formats where supported.
- Lazy-load below-the-fold images; prioritize the main hero/LCP image.
- Avoid unnecessary client-side JavaScript and duplicate requests.
- Use pagination or incremental loading for large collections.
- Prevent layout shift by reserving image dimensions.
- Measure actual performance; do not claim optimization without evidence.
