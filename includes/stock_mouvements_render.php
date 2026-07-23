<?php
/**
 * Rendu HTML des lignes mouvements de stock (table + cartes mobile).
 */

function stock_mouvement_type_label($type)
{
    if ($type === 'entree') {
        return 'Entrée';
    }
    if ($type === 'sortie') {
        return 'Sortie';
    }

    return 'Inventaire';
}

function stock_mouvement_reference_text(array $m)
{
    if (!empty($m['reference_numero'])) {
        return (string) $m['reference_numero'];
    }
    if (($m['reference_type'] ?? '') === 'commande' && !empty($m['reference_id'])) {
        return 'Commande #' . (int) $m['reference_id'];
    }

    return (string) ($m['reference_type'] ?? '-');
}

function stock_mouvements_render_table_rows(array $mouvements)
{
    ob_start();
    foreach ($mouvements as $m) {
        $type = (string) ($m['type'] ?? '');
        $badge = 'mv-badge mv-badge--' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
        $label = stock_mouvement_type_label($type);
        $ref = stock_mouvement_reference_text($m);
        ?>
        <tr class="mv-row" data-mv-type="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>">
            <td class="mv-col-date">
                <time datetime="<?php echo htmlspecialchars(date('c', strtotime($m['date_mouvement'])), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo date('d/m/Y', strtotime($m['date_mouvement'])); ?>
                </time>
                <span class="mv-col-date__time"><?php echo date('H:i', strtotime($m['date_mouvement'])); ?></span>
            </td>
            <td><span class="<?php echo $badge; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span></td>
            <td class="mv-col-produit"><?php echo htmlspecialchars($m['produit_nom'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="mv-col-qty"><?php echo (int) ($m['quantite'] ?? 0); ?></td>
            <td><?php echo $m['quantite_avant'] !== null ? (int) $m['quantite_avant'] : '—'; ?></td>
            <td><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '—'; ?></td>
            <td class="mv-col-ref"><?php echo htmlspecialchars($ref, ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="mv-col-notes"><?php echo htmlspecialchars($m['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php
    }

    return ob_get_clean() ?: '';
}

function stock_mouvements_render_cards(array $mouvements)
{
    ob_start();
    foreach ($mouvements as $m) {
        $type = (string) ($m['type'] ?? '');
        $badge = 'mv-badge mv-badge--' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
        $label = stock_mouvement_type_label($type);
        $ref = stock_mouvement_reference_text($m);
        ?>
        <article class="mv-card" data-mv-type="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>">
            <header class="mv-card__head">
                <time class="mv-card__date" datetime="<?php echo htmlspecialchars(date('c', strtotime($m['date_mouvement'])), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                    <?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?>
                </time>
                <span class="<?php echo $badge; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
            </header>
            <dl class="mv-card__body">
                <div class="mv-card__row">
                    <dt>Produit</dt>
                    <dd><?php echo htmlspecialchars($m['produit_nom'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></dd>
                </div>
                <div class="mv-card__row">
                    <dt>Quantité</dt>
                    <dd><?php echo (int) ($m['quantite'] ?? 0); ?></dd>
                </div>
                <div class="mv-card__row">
                    <dt>Avant</dt>
                    <dd><?php echo $m['quantite_avant'] !== null ? (int) $m['quantite_avant'] : '—'; ?></dd>
                </div>
                <div class="mv-card__row">
                    <dt>Après</dt>
                    <dd><?php echo $m['quantite_apres'] !== null ? (int) $m['quantite_apres'] : '—'; ?></dd>
                </div>
                <div class="mv-card__row">
                    <dt>Référence</dt>
                    <dd><?php echo htmlspecialchars($ref, ENT_QUOTES, 'UTF-8'); ?></dd>
                </div>
            </dl>
            <?php if (!empty($m['notes'])): ?>
            <p class="mv-card__notes"><?php echo htmlspecialchars($m['notes'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </article>
        <?php
    }

    return ob_get_clean() ?: '';
}

function stock_mouvements_render_pagination($page, $total_pages, $per_page, $total, array $query_base = [])
{
    if ((int) $total_pages <= 1) {
        return '';
    }

    ob_start();
    ?>
    <nav class="mv-pagination" aria-label="Pagination des mouvements" data-mv-pagination>
        <?php if ($page > 1): ?>
        <button type="button" class="mv-pagination__btn" data-mv-page="<?php echo (int) ($page - 1); ?>" aria-label="Page précédente">
            <i class="fas fa-chevron-left" aria-hidden="true"></i> Précédent
        </button>
        <?php endif; ?>

        <span class="mv-pagination__info">
            Page <strong><?php echo (int) $page; ?></strong> / <?php echo (int) $total_pages; ?>
            <span class="mv-pagination__detail">(<?php echo (int) $per_page; ?> par page · <?php echo (int) $total; ?> au total)</span>
        </span>

        <?php if ($page < $total_pages): ?>
        <button type="button" class="mv-pagination__btn" data-mv-page="<?php echo (int) ($page + 1); ?>" aria-label="Page suivante">
            Suivant <i class="fas fa-chevron-right" aria-hidden="true"></i>
        </button>
        <?php endif; ?>
    </nav>
    <?php

    return ob_get_clean() ?: '';
}
