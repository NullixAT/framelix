<?php

namespace Framelix\FramelixDemo\StorableMeta;

use Framelix\Framelix\Db\LazySearchCondition;
use Framelix\Framelix\Form\Field\File;
use Framelix\Framelix\Form\Field\Select;
use Framelix\Framelix\Html\QuickSearch;
use Framelix\Framelix\Html\Table;
use Framelix\Framelix\Html\TableCell;
use Framelix\Framelix\Storable\Storable;
use Framelix\Framelix\Storable\StorableFile;
use Framelix\Framelix\StorableMeta;
use Framelix\Framelix\Utils\ArrayUtils;
use Framelix\Framelix\View;
use Framelix\FramelixDemo\View\Incomes;

use function is_numeric;

class Income extends StorableMeta
{
    /**
     * @var \Framelix\FramelixDemo\Storable\Income
     */
    public Storable $storable;

    protected function init(): void
    {
        $this->tableDefault->initialSort = ["-receiptNumber", "-date"];
        $this->tableDefault->footerSumColumns = ['net'];

        $this->tableDefault->addColumnFlag('copyIncome', Table::COLUMNFLAG_REMOVE_IF_EMPTY);
        $property = $this->createProperty("copyIncome");
        $property->setVisibility(null, false);
        $property->setVisibility(self::CONTEXT_TABLE, true);
        $property->setLabel('');
        $property->valueCallable = function () {
            return TableCell::create('<framelix-button icon="78a" theme="primary" href="' . View::getUrl(Incomes::class)->setParameter('copy', $this->storable) . '" title="__framelixdemo_storable_income_copy__"></framelix-button>');
        };

        $this->tableDefault->addColumnFlag('downloadInvoice', Table::COLUMNFLAG_REMOVE_IF_EMPTY);
        $property = $this->createProperty("downloadInvoice");
        $property->setVisibility(null, false);
        $property->setVisibility(self::CONTEXT_TABLE, true);
        $property->setLabel('');
        $property->valueCallable = function () {
            $downloadUrl = $this->storable->invoice?->attachment?->getDownloadUrl();
            if (!$downloadUrl) {
                return null;
            }
            return TableCell::create('<framelix-button icon="709" theme="error" href="' . $downloadUrl . '" title="__framelixdemo_storable_income_download_invoice__"></framelix-button>');
        };

        $this->addDefaultPropertiesAtStart();

        $property = $this->createProperty("attachments");
        $property->field = new File();
        $property->field->multiple = true;
        $property->field->storableFileBase = new StorableFile();
        $property->valueCallable = function () {
            $arr = [];
            if ($attachments = $this->storable->getAttachments()) {
                $arr = ArrayUtils::merge($arr, $attachments);
            }
            return $arr;
        };

        $this->tableDefault->addColumnFlag('receiptNumber', Table::COLUMNFLAG_SMALLWIDTH);
        $property = $this->createProperty("receiptNumber");
        $property->valueCallable = function () {
            return $this->storable->getReceiptNumber();
        };

        $property = $this->createProperty("date");
        $property->addDefaultField();

        $property = $this->createProperty("comment");
        $property->addDefaultField();

        $property = $this->createProperty("incomeCategory");
        $property->lazySearchConditionColumns->addColumn("incomeCategory.name", "category");
        $property->setLabel('__framelixdemo_storable_income_incomecategory_label__');
        $property->addDefaultField();

        $property = $this->createProperty("net");
        $property->addDefaultField();

        $this->addDefaultPropertiesAtEnd();
    }

    public function getQuickSearch(): QuickSearch
    {
        $quickSearch = parent::getQuickSearch();
        $field = new Select();
        $fixations = \Framelix\FramelixDemo\Storable\Fixation::getByCondition(sort: '-dateFrom');
        $field->name = "fixation";
        $field->chooseOptionLabel = '__framelixdemo_search_option_choose_fixation__';
        $field->addOption("nofixation", '__framelixdemo_search_option_nofixation__');
        foreach ($fixations as $fixation) {
            $field->addOption(
                $fixation,
                $fixation->dateFrom->getHtmlString() . " - " . $fixation->dateTo->getHtmlString()
            );
        }
        $quickSearch->addOptionField($field);
        return $quickSearch;
    }

    public function getQuickSearchCondition(?array $options = null): LazySearchCondition
    {
        $condition = parent::getQuickSearchCondition($options);
        if ($options['fixation'] ?? false) {
            if ($options['fixation'] === 'nofixation') {
                $condition->prependFixedCondition = "fixation IS NULL";
            } elseif (is_numeric($options['fixation'])) {
                $condition->prependFixedCondition = "fixation = " . (int)$options['fixation'];
            }
        }
        return $condition;
    }
}