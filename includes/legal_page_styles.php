<style>
    .legal-page {
        max-width: 920px;
        margin: 40px auto;
        padding: 40px 20px 80px;
        background: var(--blanc);
        border-radius: 10px;
        box-shadow: var(--ombre-douce);
    }
    .legal-page h1 {
        color: var(--titres);
        font-size: clamp(1.5rem, 4vw, 2rem);
        margin-bottom: 24px;
        text-align: center;
        border-bottom: 3px solid var(--couleur-dominante);
        padding-bottom: 15px;
    }
    .legal-page h2 {
        color: var(--titres);
        font-size: 1.15rem;
        margin-top: 28px;
        margin-bottom: 12px;
    }
    .legal-page h3 {
        color: var(--gris-fonce);
        font-size: 1rem;
        margin-top: 18px;
        margin-bottom: 8px;
    }
    .legal-page p,
    .legal-page li {
        color: var(--texte-fonce);
        font-size: 15px;
        line-height: 1.8;
    }
    .legal-page p {
        margin-bottom: 14px;
        text-align: justify;
    }
    .legal-page ul,
    .legal-page ol {
        margin: 12px 0 16px;
        padding-left: 28px;
    }
    .legal-page li {
        margin-bottom: 8px;
    }
    .legal-page a {
        color: var(--couleur-dominante);
        text-decoration: none;
    }
    .legal-page a:hover {
        color: var(--orange);
        text-decoration: underline;
    }
    .legal-page table {
        width: 100%;
        border-collapse: collapse;
        margin: 16px 0;
        font-size: 14px;
    }
    .legal-page th,
    .legal-page td {
        border: 1px solid rgba(53, 100, 166, 0.2);
        padding: 10px 12px;
        text-align: left;
        vertical-align: top;
    }
    .legal-page th {
        background: var(--bleu-pale);
        color: var(--titres);
    }
    .legal-highlight {
        background: var(--bleu-pale);
        border-left: 4px solid var(--couleur-dominante);
        padding: 14px 16px;
        margin: 16px 0;
        border-radius: 0 8px 8px 0;
    }
    .legal-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 20px 0 28px;
        padding: 0;
        list-style: none;
    }
    .legal-nav a {
        display: inline-block;
        padding: 8px 14px;
        background: var(--blanc-casse);
        border-radius: 6px;
        font-size: 13px;
    }
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 30px;
        padding: 12px 24px;
        background: var(--couleur-dominante);
        color: var(--texte-clair);
        text-decoration: none;
        border-radius: 8px;
        transition: background 0.3s ease;
    }
    .back-link:hover {
        background: var(--orange);
        color: var(--texte-clair);
    }
</style>
