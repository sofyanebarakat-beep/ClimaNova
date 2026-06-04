# AIO — AI Overview Optimization Skill
## Get featured in Google AI Overviews (SGE) for ClimaNova Énergie

**Website:** https://climanova-energie.fr
**CTA:** https://climanova-energie.fr/demande-devis/

---

## WHAT IS AIO

AI Overview Optimization (AIO) is the practice of structuring content so Google's
AI-powered Search Generative Experience (SGE / AI Overviews) selects your content
as the primary source to summarize and display at the top of search results.

Google AI Overviews appear for 30–40% of queries. Being cited means:
- Zero-click brand visibility
- Traffic from users who want to learn more
- Authority signal for organic rankings

---

## INPUT

```
Page URL       : https://climanova-energie.fr/[page]
Target Query   : [exact query you want to appear in AI Overview for]
Content Type   : [article / service page / FAQ page / landing page]
Language       : French
```

---

## PROMPT

You are an AIO (AI Overview Optimization) expert. Your goal is to restructure and
optimize content from https://climanova-energie.fr so that Google's AI Overview
selects it as a primary source when users search for [TARGET QUERY].

All output in French.

---

### PHASE 1 — AI OVERVIEW TRIGGER ANALYSIS

Identify which query types trigger AI Overviews for this topic:

1. **Definition queries** — "Qu'est-ce que [TOPIC] ?"
2. **How-to queries** — "Comment [action] ?"
3. **Comparison queries** — "[A] vs [B] — lequel choisir ?"
4. **Cost queries** — "Combien coûte [TOPIC] ?"
5. **Best practice queries** — "Comment bien choisir [TOPIC] ?"
6. **Local queries** — "[TOPIC] à [CITY] — qui contacter ?"

For each trigger, rate:
- Likelihood of AI Overview appearance (High / Medium / Low)
- Current content coverage (Covered / Partial / Missing)
- Priority to optimize (1–5)

---

### PHASE 2 — CONTENT AUDIT FOR AI OVERVIEW

Analyze the existing page and identify:

**What AI Overviews need to extract this page:**
- [ ] Clear H1 that matches query intent
- [ ] Definition within first 100 words
- [ ] Numbered lists for processes
- [ ] Bullet points for features/benefits
- [ ] Tables for comparisons
- [ ] Direct Q&A format sections
- [ ] Author/source credibility signals
- [ ] FAQPage schema markup
- [ ] Article schema markup
- [ ] Statistics with sources
- [ ] Concise summary paragraph (150 words max)

**Gap analysis:** List every missing element.

---

### PHASE 3 — AI OVERVIEW CONTENT BLOCKS

Generate optimized content blocks Google AI Overview will extract:

#### A. Featured Snippet Paragraph (40–60 words)
Write one paragraph that directly answers [TARGET QUERY] in 40–60 words.
This is the most likely AI Overview source. Requirements:
- Starts with the query keyword
- Answers completely in first 2 sentences
- Includes key facts/numbers
- No marketing language

#### B. Definition Box
```
**[TERM]** est [category] qui [function/purpose].
Il se caractérise par [key feature 1], [key feature 2] et [key feature 3].
En France, [relevant regulation or context].
```

#### C. "How it Works" — Numbered Steps (AI-extractable)
```
1. [Step 1] : [one sentence explanation]
2. [Step 2] : [one sentence explanation]
3. [Step 3] : [one sentence explanation]
[continue for all steps]
```

#### D. Key Facts Box (for AI to cite as bullet summary)
```
Points clés :
• [Fact 1 with number/stat]
• [Fact 2 with source]
• [Fact 3 actionable]
• [Fact 4 local/specific]
• [Fact 5 about ClimaNova]
```

#### E. Quick Comparison Table (AI extracts tables frequently)
| Critère | [Option A] | [Option B] | [Option C] |
|---|---|---|---|
| Prix | X€ | Y€ | Z€ |
| Efficacité | ★★★★★ | ★★★★ | ★★★ |
| Installation | X jours | Y jours | Z jours |
| Aide MaPrimeRénov' | Oui | Non | Partiel |

#### F. Cost Summary (AI Overviews cite pricing heavily)
```
Tarifs [TOPIC] en France (2025) :
• Entrée de gamme : [X]€ à [Y]€
• Milieu de gamme : [X]€ à [Y]€
• Haut de gamme   : [X]€ à [Y]€
• Pose incluse    : +[X]€ à +[Y]€
• Après aides (MaPrimeRénov') : à partir de [X]€
```

---

### PHASE 4 — FAQ OPTIMIZATION FOR AI OVERVIEWS

Generate 20 FAQs formatted for maximum AI Overview extraction:

**Format rules:**
- Question = exact query users type in Google
- Answer = 40–80 words maximum
- Answer starts with the keyword from the question
- Includes 1 specific number or fact
- Ends with a soft CTA or reference to ClimaNova

Example format:
```
**Q : Quel est le prix d'une pompe à chaleur air/air en 2025 ?**
A : Le prix d'une pompe à chaleur air/air varie entre 1 500€ et 5 000€ selon
la puissance et la marque. L'installation est comprise entre 500€ et 1 500€
supplémentaires. Avec MaPrimeRénov', la prime peut atteindre 4 000€.
ClimaNova Énergie propose des devis gratuits pour évaluer votre projet.
```

---

### PHASE 5 — SCHEMA MARKUP FOR AI OVERVIEWS

Generate all schema types that influence AI Overview selection:

#### FAQPage Schema (all 20 FAQs)
```json
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "[Question 1]",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "[Answer 1 — same as FAQ content]"
      }
    }
  ]
}
```

#### HowTo Schema (for process content)
```json
{
  "@context": "https://schema.org",
  "@type": "HowTo",
  "name": "Comment [action]",
  "description": "[150-char summary]",
  "step": [
    {
      "@type": "HowToStep",
      "name": "[Step name]",
      "text": "[Step description]"
    }
  ]
}
```

#### Article Schema with speakable (for voice/AI)
```json
{
  "@context": "https://schema.org",
  "@type": "Article",
  "speakable": {
    "@type": "SpeakableSpecification",
    "cssSelector": ["h1", ".article-summary", ".key-facts"]
  }
}
```

---

### PHASE 6 — ON-PAGE OPTIMIZATION CHECKLIST

Verify every element Google AI Overview needs:

**Structure:**
- [ ] H1 matches primary query intent
- [ ] Definition in first paragraph (within 100 words)
- [ ] Summary box after introduction
- [ ] Numbered lists for every process
- [ ] Bullet points for features/benefits/pros/cons
- [ ] Tables for comparisons and pricing
- [ ] FAQ section with 15+ Q&As

**Authority signals:**
- [ ] Author name + credentials mentioned
- [ ] Organization name (ClimaNova Énergie) mentioned in first 100 words
- [ ] RGE certification mentioned
- [ ] Publication date visible
- [ ] Last updated date visible
- [ ] References to ADEME, ANAH, official sources

**Technical:**
- [ ] FAQPage JSON-LD
- [ ] Article JSON-LD
- [ ] HowTo JSON-LD (if applicable)
- [ ] Page loads in < 2.5s (Core Web Vitals)
- [ ] Mobile-friendly
- [ ] HTTPS

---

### PHASE 7 — AI OVERVIEW MONITORING

Weekly monitoring process:

Search these queries in Google (incognito, France location):
1. "[Primary keyword]"
2. "Comment [action related to topic]"
3. "Prix [topic] 2025"
4. "Meilleur [topic] France"
5. "[Topic] certifié RGE"

Record:
- AI Overview shown? Y/N
- ClimaNova cited? Y/N
- Which content block was cited?
- Which competitor was cited instead?
- What to improve?

---

## OUTPUT ORDER

1. AI Overview Trigger Analysis (all query types)
2. Content Audit results (gap list)
3. Featured Snippet Paragraph
4. Definition Box
5. How-It-Works Steps
6. Key Facts Box
7. Comparison Table
8. Cost Summary
9. 20 FAQs (AI Overview optimized)
10. FAQPage JSON-LD (all 20)
11. HowTo JSON-LD
12. Article + Speakable JSON-LD
13. On-page Optimization Checklist
14. Monitoring Query List
