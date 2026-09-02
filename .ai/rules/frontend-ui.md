# Frontend UI rules

## Dropdowns

- Do not render native HTML `<select>` elements in product UI.
- Use the shared `SelectField` component from `frontend/src/components/select-field.tsx` for every dropdown.
- Extend the shared component when a dropdown needs new behavior; do not create page-specific dropdown markup.
- Dropdowns must support keyboard operation, visible selected state, localized labels, mobile layout, and existing design tokens.
- Before finishing a frontend change, run `rg -n "<select|</select>" frontend/src --glob "*.tsx"`; the result must be empty.

## Responsive containment

- Drawers, modals, forms, and grid children must never create page-level horizontal scrolling.
- Use `minmax(0, 1fr)` for flexible grid tracks and `min-width: 0` on grid/flex children that contain form controls.
- Keep overlays within `100vw`; drawers may scroll vertically but must clip unintended horizontal overflow.
- Verify changed overlays at desktop and mobile widths before finishing.
