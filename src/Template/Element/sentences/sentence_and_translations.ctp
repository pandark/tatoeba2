<?php
use App\Lib\LanguagesLib;

$this->Html->script('/js/directives/sentence-and-translations.dir.js', array('block' => 'scriptBottom'));

list($directTranslations, $indirectTranslations) = $translations;
$maxDisplayed = 5;
$displayedTranslations = 0;
$showExtra = '';
$numExtra = count($directTranslations) + count($indirectTranslations) - $maxDisplayed;
$sentenceLink = $this->Html->link(
    '#'.$sentence->id,
    array(
        'controller' => 'sentences',
        'action' => 'show',
        $sentence->id
    )
);
$sentenceUrl = $this->Url->build(array(
    'controller' => 'sentences',
    'action' => 'show',
    $sentence->id
));
$notReliable = $sentence->correctness == -1;

$sentenceText = h($sentence->text);
if (isset($sentence->highlight)) {
    $highlight = $sentence->highlight;
    $sentenceText = $this->Search->highlightMatches($highlight, $sentenceText);
}

?>
<div sentence-and-translations class="sentence-and-translations" md-whiteframe="1">
    <div layout="column">
        <md-subheader>
            <?
            if ($user) {
                $userLink = $this->Html->link(
                    $user->username,
                    array(
                        'controller' => 'user',
                        'action' => 'profile',
                        $user->username
                    )
                );
                echo format(
                    __('Sentence {number} — belongs to {username}'),
                    array(
                        'number' => $sentenceLink,
                        'username' => $userLink
                    )
                );
            } else {
                echo format(
                    __('Sentence {number}'),
                    array(
                        'number' => $sentenceLink
                    )
                );
            }            
            ?>
        </md-subheader>
        <div class="sentence <?= $notReliable ? 'not-reliable' : '' ?>"
             layout="row" layout-align="start center">
            <div class="lang">
                <?
                echo $this->Languages->icon(
                    $sentence->lang,
                    array(
                        'width' => 30,
                        'height' => 20
                    )
                );
                ?>
            </div>
            <div class="text" flex
                 dir="<?= LanguagesLib::getLanguageDirection($sentence->lang) ?>">
                <?= $sentenceText ?>
            </div>
            <? if ($notReliable) { ?>
                <md-icon class="md-warn">warning</md-icon>
                <md-tooltip md-direction="top">
                    <? echo __('This sentence is not reliable.') ?>
                </md-tooltip>
            <? } ?>
            <md-button class="md-icon-button" href="<?= $sentenceUrl ?>">
                <md-icon>info</md-icon>
            </md-button>
        </div>
    </div>

    <? if (count($directTranslations) > 0) { ?>
        <div layout="column" class="direct translations">
            <md-divider></md-divider>
            <md-subheader><? echo __('Translations') ?></md-subheader>
            <? foreach ($directTranslations as $translation) {
                $isExtra = $numExtra > 1 && $displayedTranslations >= $maxDisplayed;
                echo $this->element(
                    'sentences/translation',
                    array(
                        'sentenceId' => $sentence->id,
                        'translation' => $translation,
                        'isExtra' => $isExtra
                    )
                );
                $displayedTranslations++;
            }
            ?>
        </div>
    <? } ?>

    <? if (count($indirectTranslations) > 0) {
        if ($numExtra > 1 && $displayedTranslations >= $maxDisplayed) {
            $showExtra = 'ng-if="vm.isExpanded"';
        }
        ?>
        <div layout="column" <?= $showExtra ?> class="indirect translations">
            <md-subheader><? echo __('Translations of translations') ?></md-subheader>
            <? foreach ($indirectTranslations as $translation) {
                $isExtra = $numExtra > 1 && $displayedTranslations >= $maxDisplayed;
                echo $this->element(
                    'sentences/translation',
                    array(
                        'sentenceId' => $sentence->id,
                        'translation' => $translation,
                        'isExtra' => $isExtra
                    )
                );
                $displayedTranslations++;
            } ?>
        </div>
    <? } ?>

    <? if ($numExtra > 1) { ?>
        <div layout="column">
            <md-button ng-click="vm.expandOrCollapse()">
                <md-icon>{{vm.expandableIcon}}</md-icon>
                <span ng-if="!vm.isExpanded">
                    <?php
                    echo format(__n(
                        'Show 1 more translation',
                        'Show {number} more translations',
                        $numExtra,
                        true
                    ), array('number' => $numExtra))
                    ?>
                </span>
                <span ng-if="vm.isExpanded">
                    <?php echo __('Fewer translations') ?>
                </span>
            </md-button>
        </div>
    <? } ?>
</div>
