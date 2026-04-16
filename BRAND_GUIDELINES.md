# ClimaNova — Brand Guidelines
**Version 1.0 · April 2026**

---

## Table of Contents
1. [Brand Overview](#1-brand-overview)
2. [Color System](#2-color-system)
3. [Typography](#3-typography)
4. [UI Style Direction](#4-ui-style-direction)
5. [Logo Usage](#5-logo-usage)
6. [Iconography](#6-iconography)
7. [Social Media Style Guide](#7-social-media-style-guide)
8. [Tone of Voice](#8-tone-of-voice)

---

## 1. Brand Overview

### Brand Personality

ClimaNova is a modern, tech-forward digital brand built around clarity, trust, and performance. Every visual and verbal decision reflects these three values.

| Dimension       | Description                                                                 |
|-----------------|-----------------------------------------------------------------------------|
| **Tone**        | Confident, clear, approachable — never jargon-heavy or cold                 |
| **Values**      | Innovation, transparency, reliability, sustainability-awareness             |
| **Positioning** | A digital-first brand that bridges technology with human-centered outcomes  |
| **Energy**      | Clean momentum — driven but never aggressive                                |

### Brand Personality Spectrum
```
Formal ◄────────────────────────── ● ───────► Playful
Cold   ◄──────── ● ───────────────────────── ► Warm
Simple ◄─────────────────────── ● ──────────► Complex
```
ClimaNova sits slightly right of center — professional enough for enterprise, approachable enough for end users.

### Suggested Usage Contexts
- Web & mobile applications
- Marketing landing pages
- SaaS dashboards
- Social media (LinkedIn, Instagram, X/Twitter)
- Email campaigns and newsletters
- Pitch decks and investor materials

---

## 2. Color System

### Primary Colors

| Role          | Name           | Hex       | RGB              | HSL              |
|---------------|----------------|-----------|------------------|------------------|
| Primary       | ClimaNova Blue | `#1664BF` | 22, 100, 191     | 212°, 79%, 42%   |
| Secondary     | ClimaNova Green| `#03BE63` | 3, 190, 99       | 149°, 97%, 38%   |

### Supporting Neutrals

These neutrals are extracted directly from the design system and match the existing codebase.

| Token        | Hex       | Usage                                      |
|--------------|-----------|--------------------------------------------|
| Neutral 1    | `#0B0B0B` | Headings on light backgrounds              |
| Neutral 2    | `#222222` | Primary body text                          |
| Neutral 3    | `#3A3A39` | Secondary body text, icons                 |
| Neutral 4    | `#515150` | Tertiary text, captions                    |
| Neutral 5    | `#696867` | Placeholder text, disabled labels          |
| Neutral 6    | `#80807E` | Borders on dark surfaces                   |
| Neutral 7    | `#979794` | Dividers                                   |
| Neutral 8    | `#AFAEAB` | Subtle decorative elements                 |
| Neutral 9    | `#C6C5C2` | Disabled button strokes                    |
| Neutral 10   | `#DEDDD9` | Input borders, card borders                |
| Neutral 11   | `#E9E8E5` | Background tints, section dividers         |
| White        | `#FFFFFF` | Page background, reversed text             |

### Accent & State Colors

| Role         | Hex       | Usage                                      |
|--------------|-----------|--------------------------------------------|
| Primary Dark | `#0F4D96` | Hover/active state for primary buttons     |
| Primary Tint | `#EAF1FC` | Highlight backgrounds, info banners        |
| Green Dark   | `#029C52` | Hover/active state for secondary elements  |
| Green Tint   | `#E6FBF1` | Success state backgrounds                  |
| Warning      | `#FBBB07` | Warning badges, alerts                     |
| Error        | `#E03131` | Destructive actions, error states          |

### Color Usage Rules

**Do:**
- Use `#1664BF` as the primary action color (CTAs, links, active navigation)
- Use `#03BE63` for success states, positive data, and supporting CTAs
- Pair blue on white — minimum contrast ratio 4.5:1 for body text (WCAG AA)
- Use neutrals for all long-form text
- Use tint variants for large background areas — never saturate the canvas

**Don't:**
- Place `#1664BF` text on `#03BE63` background (insufficient contrast)
- Use both Primary Blue and Primary Green at equal visual weight in the same component
- Introduce new brand colors without approval
- Use Warning yellow as a decorative color

### Accessibility Contrast Reference

| Foreground   | Background  | Ratio  | WCAG Level |
|--------------|-------------|--------|------------|
| `#1664BF`    | `#FFFFFF`   | 5.2:1  | AA         |
| `#FFFFFF`    | `#1664BF`   | 5.2:1  | AA         |
| `#03BE63`    | `#FFFFFF`   | 3.2:1  | AA Large   |
| `#FFFFFF`    | `#03BE63`   | 3.2:1  | AA Large   |
| `#0B0B0B`    | `#FFFFFF`   | 19.6:1 | AAA        |
| `#222222`    | `#FFFFFF`   | 15.3:1 | AAA        |

> For `#03BE63` on white, use font size 18px+ or font-weight 700+ to meet WCAG AA.

---

## 3. Typography

### Primary Typeface — Instrument Sans

All text across web, mobile, and marketing materials uses **Instrument Sans** exclusively.

```
font-family: "Instrument Sans", sans-serif;
Import: https://fonts.google.com/specimen/Instrument+Sans
Weights used: 300 · 400 · 500 · 600 · 700
```

### Type Scale

| Token  | Size  | Weight | Letter Spacing | Line Height | Usage                              |
|--------|-------|--------|----------------|-------------|------------------------------------|
| H1     | 56px  | 700    | -0.0263em      | 1.1         | Hero headlines, page titles        |
| H2     | 43px  | 700    | -0.027em       | 1.15        | Section headings                   |
| H3     | 32px  | 600    | -0.027em       | 1.2         | Card titles, subsection headings   |
| H4     | 26px  | 600    | 0em            | 1.3         | Feature labels, modal titles       |
| H5     | 24px  | 500    | 0em            | 1.4         | List headings, sidebar titles      |
| H6     | 20px  | 500    | 0em            | 1.4         | Overlines, small section labels    |
| XL     | 21px  | 400    | 0em            | 1.55        | Lead paragraphs, intros            |
| LG     | 17px  | 400    | 0em            | 1.6         | Body text (articles, descriptions) |
| MD/SM  | 16px  | 400    | 0em            | 1.5         | UI body text, form labels          |
| Caption| 14px  | 400    | 0em            | 1.4         | Timestamps, metadata, footnotes    |
| Micro  | 12px  | 400    | 0.01em         | 1.3         | Legal text, badges                 |

### Typography Rules

- **Headlines (H1–H3):** Always use negative letter spacing (tracked tighter than normal)
- **Body text:** Use Neutral 2 (`#222`) on white; Neutral 11 (`#E9E8E5`) on dark surfaces
- **Never** use font-weight 300 for anything smaller than 18px
- **Avoid** mixing more than 2 weight levels in a single component
- **Line length:** Keep body text between 55–75 characters per line for optimal readability
- **UI labels:** Use Medium (500) for interactive labels; Regular (400) for static content

---

## 4. UI Style Direction

### Border Radius System

| Token    | Value  | Usage                                          |
|----------|--------|------------------------------------------------|
| xs       | 10px   | Chips, small tags                              |
| sm       | 12px   | Input fields, small cards                      |
| md       | 16px   | Cards, modals, dropdowns                       |
| lg       | 20px   | Large cards, panels                            |
| xl       | 22px   | Feature blocks                                 |
| 2xl      | 50px   | Pill containers, full-width banners            |
| 3xl      | 55px   | Hero callout shapes                            |
| 4xl      | 100px  | Circular avatars, pill buttons                 |

### Spacing System

| Token  | Value | Usage                                           |
|--------|-------|-------------------------------------------------|
| 0      | 0px   | Flush/reset                                     |
| xs     | 10px  | Tight element gaps (icon+label, badge padding)  |
| sm     | 12px  | Form item gaps                                  |
| md     | 16px  | Default component padding                       |
| lg     | 24px  | Section inner padding, card body padding        |
| xl     | 24px  | Grid column gap                                 |
| 2xl    | 32px  | Component group spacing                         |
| 3xl    | 40px  | Large layout gaps                               |
| 4xl    | 60px  | Section spacing (mobile)                        |
| 5xl    | 80px  | Section spacing (desktop)                       |

Section padding:
- Mobile (md): `60px` top/bottom
- Tablet (lg): `70px` top/bottom
- Desktop (xl): `100px` top/bottom

---

### Button Styles

#### Primary Button
```css
background:    #1664BF;
color:         #FFFFFF;
font-family:   "Instrument Sans", sans-serif;
font-size:     16px;
font-weight:   600;
border-radius: 100px;         /* radius4xl — pill shape */
padding:       14px 28px;
border:        none;
letter-spacing: 0em;
transition:    background 0.2s ease, transform 0.15s ease;
```
- **Hover:** `background: #0F4D96`
- **Active:** `background: #0A3A72; transform: scale(0.98)`
- **Disabled:** `background: #C6C5C2; color: #80807E; cursor: not-allowed`

#### Secondary Button (Outlined)
```css
background:    transparent;
color:         #1664BF;
border:        1.5px solid #1664BF;
font-size:     16px;
font-weight:   600;
border-radius: 100px;
padding:       14px 28px;
transition:    background 0.2s ease, color 0.2s ease;
```
- **Hover:** `background: #EAF1FC; color: #1664BF`
- **Active:** `background: #D0E4F8`

#### Ghost / Text Button
```css
background:    transparent;
color:         #1664BF;
border:        none;
font-weight:   500;
padding:       10px 16px;
border-radius: 12px;
```
- **Hover:** `background: #EAF1FC`

#### Accent (Green) Button
```css
background:    #03BE63;
color:         #FFFFFF;
border-radius: 100px;
font-weight:   600;
padding:       14px 28px;
```
- **Hover:** `background: #029C52`
- Use sparingly — for positive actions (confirm, subscribe, success CTA)

---

### Cards

```css
background:    #FFFFFF;
border:        1px solid #E9E8E5;
border-radius: 20px;           /* radiuslg */
padding:       32px;
box-shadow:    0 2px 16px rgba(0, 0, 0, 0.06);
transition:    box-shadow 0.2s ease, transform 0.2s ease;
```
- **Hover:** `box-shadow: 0 8px 32px rgba(22, 100, 191, 0.1); transform: translateY(-2px)`

---

### Forms

```css
/* Input field */
height:           52px;
padding:          0 16px;
border:           1.5px solid #DEDDD9;
border-radius:    12px;           /* radiussm */
font-size:        16px;
font-weight:      400;
color:            #222222;
background:       #FFFFFF;
outline:          none;
transition:       border-color 0.2s ease;

/* Focus state */
border-color:     #1664BF;
box-shadow:       0 0 0 3px rgba(22, 100, 191, 0.12);

/* Error state */
border-color:     #E03131;
box-shadow:       0 0 0 3px rgba(224, 49, 49, 0.1);

/* Label */
font-size:        14px;
font-weight:      500;
color:            #3A3A39;
margin-bottom:    6px;
```

---

## 5. Logo Usage

### Clear Space
- Maintain a minimum clear space equal to the height of the "C" letterform on all sides of the logo
- Never crowd the logo with other visual elements

### Minimum Size
- Digital: 120px wide minimum
- Print: 30mm wide minimum
- Favicon / App icon: 32×32px minimum (use icon-only lockup)

### Approved Backgrounds
| Surface              | Logo Variant              |
|----------------------|---------------------------|
| White `#FFFFFF`      | Full color (primary)       |
| Light gray `#E9E8E5` | Full color (primary)       |
| Primary Blue `#1664BF`| White/reversed version    |
| Dark `#0B0B0B`       | White/reversed version     |
| Photography          | White version with overlay |

### Do Not
- Stretch, skew, or rotate the logo
- Change logo colors outside approved variants
- Place logo on busy backgrounds without sufficient contrast
- Apply drop shadows or effects to the logo
- Use the wordmark below minimum size — use icon-only at small sizes

---

## 6. Iconography

### Style Direction
- **Style:** Line/outline icons with 1.5px stroke weight
- **Corner style:** Rounded caps and joins (matching `border-radius: 12px` system)
- **Grid:** 24×24px base grid; scale to 16px, 20px, 32px, 48px as needed
- **Recommended library:** Lucide Icons or Phosphor Icons (outline variant)

### Consistency Rules
- All icons in a single view must share the same size and stroke weight
- Use Neutral 3 (`#3A3A39`) as the default icon color
- Use Primary Blue (`#1664BF`) for interactive/active icons
- Use Green (`#03BE63`) for success/positive state icons
- Use `#E03131` for error/destructive icons
- Never mix outline and filled styles in the same component
- Icons used alongside text: align to text optical center, not bounding box

---

## 7. Social Media Style Guide

### Platform Guidelines

| Platform   | Format            | Primary Style                             |
|------------|-------------------|--------------------------------------------|
| LinkedIn   | 1200×627px        | Professional, minimal, data-forward        |
| Instagram  | 1080×1080px       | Visual-first, bold type, clean backgrounds |
| X/Twitter  | 1600×900px        | High contrast, short headlines             |
| Stories    | 1080×1920px       | Full-bleed gradient, large type            |

### Gradient System

The brand gradient combines Primary Blue and Secondary Green:

```css
/* Primary Gradient — Hero, CTAs, Story backgrounds */
background: linear-gradient(135deg, #1664BF 0%, #03BE63 100%);

/* Subtle Gradient — Cards, banners (tint version) */
background: linear-gradient(135deg, #EAF1FC 0%, #E6FBF1 100%);

/* Dark Gradient — Overlays on photography */
background: linear-gradient(180deg, rgba(22,100,191,0.0) 0%, rgba(22,100,191,0.72) 100%);
```

### Post Style Direction

**Feed Posts:**
- Background: White `#FFFFFF` or Neutral 11 `#E9E8E5` or the subtle gradient
- Headline: H2 or H3 weight, Neutral 1
- One primary CTA element per post
- Leave 40px+ breathing room from edges

**Story / Reel Covers:**
- Use the primary gradient as full-bleed background
- White text only (H2 size, 700 weight)
- Logo in top-left corner with 40px inset
- Max 6 words in headline

**Data / Stat Posts:**
- Large number: H1 size, `#1664BF`
- Supporting label: text-md, Neutral 4
- Background: White with subtle blue tint border

### Template Suggestions

1. **Announcement:** Full-bleed gradient + white headline + logo bottom-right
2. **Testimonial:** White card on neutral background + quotation mark in `#1664BF` + avatar + 4xl border radius
3. **Stat highlight:** Large number in Primary Blue, supporting copy in Neutral 3, minimal divider line
4. **Product feature:** Left-aligned text block + right-side UI screenshot, white background
5. **Event/launch:** Dark overlay on photo + white text + green accent tag

---

## 8. Tone of Voice

### Writing Principles

| Principle     | Meaning in Practice                                                      |
|---------------|--------------------------------------------------------------------------|
| **Clear**     | One idea per sentence. No filler words. Cut anything that doesn't add value. |
| **Confident** | State facts directly. Avoid "we think" or "we believe" hedges.           |
| **Human**     | Write like a knowledgeable colleague, not a press release.               |
| **Forward**   | Focus on outcomes and possibilities, not features alone.                 |

### Vocabulary

**Use:**
- Precise, active verbs: "build," "launch," "deliver," "track," "connect"
- Outcome-first language: "So you can…", "That means…"
- Numbers when they add credibility

**Avoid:**
- Buzzwords: "synergy," "leverage," "game-changing," "disruptive"
- Passive voice in CTAs
- Exclamation marks in professional contexts
- ALL CAPS for emphasis (use bold instead)

### Example Messaging

| Context             | Example Copy                                                          |
|---------------------|-----------------------------------------------------------------------|
| Hero headline       | "Built for the way teams actually work."                              |
| Sub-headline        | "ClimaNova brings your data, tools, and people into one clear view."  |
| CTA button          | "Get started" / "See how it works" / "Book a demo"                   |
| Error message       | "Something went wrong. Try again or contact support."                 |
| Success message     | "Done. Your changes have been saved."                                 |
| Empty state         | "Nothing here yet. Start by creating your first project."             |
| Social caption      | "The less time you spend managing tools, the more time you have to build. ClimaNova." |
| Email subject line  | "Your dashboard is ready — here's what to do next"                   |

### Capitalization
- Headlines: Sentence case (only first word + proper nouns)
- Navigation & buttons: Sentence case
- All-caps: Only for micro-labels (e.g., "NEW", "BETA") — never for body text

---

*ClimaNova Brand Guidelines v1.0 — April 2026*
*For questions, contact the brand team before deviating from any specification in this document.*
