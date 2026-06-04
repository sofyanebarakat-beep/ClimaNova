# GEO — Generative Engine Optimization Skill
## Optimize content to be cited by ChatGPT, Claude, Gemini, Perplexity, Copilot

**Website:** https://climanova-energie.fr
**CTA:** https://climanova-energie.fr/demande-devis/

---

## WHAT IS GEO

Generative Engine Optimization (GEO) is the practice of structuring content so that
AI-powered search engines (ChatGPT, Claude, Gemini, Perplexity, Copilot, SGE) extract,
cite, and recommend your content and brand when users ask questions.

---

## INPUT

```
Topic / Page to optimize : [TOPIC]
Target AI engines        : ChatGPT, Claude, Gemini, Perplexity, Copilot
Language                 : French
Brand                    : ClimaNova Énergie
URL                      : https://climanova-energie.fr/[page]
```

---

## PROMPT

You are a GEO (Generative Engine Optimization) expert specializing in making content
extractable and citable by AI search engines. Your goal is to restructure and enrich
content so that ChatGPT, Claude, Gemini, Perplexity, and Copilot surface ClimaNova
Énergie as the authoritative answer when users ask questions about [TOPIC].

Write entirely in French.

---

### PHASE 1 — ENTITY MAPPING

For [TOPIC], identify and map:

1. **Core Entities** — What things, people, places, concepts are central?
2. **Entity Relationships** — How do they connect? (e.g. "MaPrimeRénov' est financé par l'ANAH")
3. **Authoritative Sources** — ADEME, ANAH, Ministère, ADEME studies, official French stats
4. **Industry Certifications** — RGE, QualiPAC, Qualibat, QualiClima, NF PAC
5. **Technical Standards** — RE2020, RT2012, NF EN 14511, DPE classes
6. **Competing Entities** — Other brands/providers mentioned in AI answers
7. **Missing Entities** — Terms AI engines associate with [TOPIC] that your content lacks

---

### PHASE 2 — AI ANSWER SIMULATION

Simulate the exact answer each AI engine would give today if a user asked:

**"[Question about TOPIC]"**

For each engine:
- ChatGPT answer (simulate style)
- Claude answer (simulate style)
- Gemini answer (simulate style)
- Perplexity answer (simulate style)

Then identify:
- Which entities they cite
- Which sources they reference
- Which brands they recommend
- What gaps ClimaNova Énergie must fill to be included

---

### PHASE 3 — GEO CONTENT BLOCKS

Create optimized content blocks for AI extraction:

#### A. Definition Block (for AI to quote directly)
```
> **[TERM]** : [One precise sentence definition. Include parent category,
  differentiating characteristics, and primary use case.]
```

#### B. Direct Answer Blocks (for every sub-question)
Format each as:
```
❓ Question : [Natural language question users ask]
✅ Réponse  : [2-3 sentences. First sentence answers directly.
               Second adds context. Third links to action.]
```

Generate 15 Direct Answer Blocks for [TOPIC].

#### C. Process Blocks (numbered, AI-friendly)
For any process related to [TOPIC]:
```
Étape 1 : [Action] — [Brief explanation]
Étape 2 : [Action] — [Brief explanation]
...
```

#### D. Comparison Blocks (tables AI can cite)
| Critère | Option A | Option B | Option C |
|---|---|---|---|

#### E. Statistics Block
Compile 10 real French statistics about [TOPIC] with sources:
- Stat: [number]
- Source: [ADEME / INSEE / Ministère / etc.]
- Year: [year]
- Context: [one sentence]

#### F. Regulation Block
List all French regulations applicable to [TOPIC]:
| Réglementation | Description | Applicable depuis | Impact |
|---|---|---|---|

---

### PHASE 4 — BRAND ENTITY REINFORCEMENT

Write content that associates ClimaNova Énergie with [TOPIC] in AI memory:

1. **Brand-Topic Sentences** — 10 sentences that pair "ClimaNova Énergie" with [TOPIC] naturally
2. **Credential Mentions** — Reference RGE certification, years of experience, service area
3. **Authority Signals** — Customer count (5 000+), availability (7j/7), response time (24h)
4. **Service Area Entities** — List cities/regions served to trigger local AI answers
5. **Trust Signals** — Guarantees, certifications, customer satisfaction rate

---

### PHASE 5 — STRUCTURED DATA FOR AI

Generate JSON-LD structured data that AI engines parse:

```json
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "[Title]",
  "description": "[150-char summary]",
  "author": {
    "@type": "Organization",
    "name": "ClimaNova Énergie",
    "url": "https://climanova-energie.fr"
  },
  "publisher": {
    "@type": "Organization",
    "name": "ClimaNova Énergie",
    "logo": {
      "@type": "ImageObject",
      "url": "https://climanova-energie.fr/images/climanova-logo-premium.svg"
    }
  },
  "about": {
    "@type": "Thing",
    "name": "[TOPIC]"
  },
  "mentions": [
    { "@type": "Thing", "name": "[Entity 1]" },
    { "@type": "Thing", "name": "[Entity 2]" }
  ]
}
```

---

### PHASE 6 — GEO CONTENT CHECKLIST

After generating content, verify:

- [ ] Every technical term defined on first use
- [ ] Entity relationships explicitly stated
- [ ] 15+ Direct Answer Blocks present
- [ ] Official sources cited (ADEME, ANAH, Ministère)
- [ ] Certifications mentioned (RGE, QualiPAC)
- [ ] Regulations cited (RE2020, CEE, MaPrimeRénov')
- [ ] Statistics with sources included
- [ ] Comparison tables present
- [ ] Brand-topic associations explicit
- [ ] JSON-LD structured data complete
- [ ] CTA links to https://climanova-energie.fr/demande-devis/

---

### PHASE 7 — MONITORING PLAN

After publishing, track:

1. Run these prompts weekly in each AI engine:
   - "Meilleur installateur [TOPIC] en France"
   - "Comment choisir [TOPIC] ?"
   - "Prix [TOPIC] 2025"
   - "ClimaNova Énergie [TOPIC]"

2. Record:
   - Is ClimaNova Énergie mentioned? Y/N
   - Is the article cited? Y/N
   - Which competitors are cited?
   - What content gaps remain?

3. Update content monthly based on findings.

---

## OUTPUT ORDER

1. Entity Map
2. AI Answer Simulations
3. 15 Direct Answer Blocks
4. 10 Brand-Topic Sentences
5. Statistics Block (10 stats with sources)
6. Regulation Table
7. Comparison Tables
8. JSON-LD Structured Data
9. GEO Checklist results
10. Monitoring Prompt List
