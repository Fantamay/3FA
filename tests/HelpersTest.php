<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour config/helpers.php
 * Couvre : get_categories(), category_color()
 */
class HelpersTest extends TestCase
{
    // ------------------------------------------------------------------
    // get_categories()
    // ------------------------------------------------------------------

    public function testGetCategoriesReturnsArray(): void
    {
        $categories = get_categories();
        $this->assertIsArray($categories);
    }

    public function testGetCategoriesIsNotEmpty(): void
    {
        $this->assertNotEmpty(get_categories());
    }

    public function testGetCategoriesContainsExpectedValues(): void
    {
        $categories = get_categories();
        $this->assertContains('Ordonnances', $categories);
        $this->assertContains('Radios / Imagerie', $categories);
        $this->assertContains('Analyses / Biologie', $categories);
        $this->assertContains('Vaccins', $categories);
        $this->assertContains('Comptes-rendus', $categories);
        $this->assertContains('Autres', $categories);
    }

    public function testGetCategoriesCountIsSix(): void
    {
        $this->assertCount(6, get_categories());
    }

    public function testGetCategoriesContainsOnlyStrings(): void
    {
        foreach (get_categories() as $cat) {
            $this->assertIsString($cat);
        }
    }

    // ------------------------------------------------------------------
    // category_color()
    // ------------------------------------------------------------------

    public function testCategoryColorReturnsStringForKnownCategory(): void
    {
        $color = category_color('Ordonnances');
        $this->assertIsString($color);
        $this->assertNotEmpty($color);
    }

    public function testCategoryColorStartsWithHash(): void
    {
        foreach (get_categories() as $cat) {
            $color = category_color($cat);
            $this->assertStringStartsWith('#', $color, "La couleur de '$cat' doit commencer par #");
        }
    }

    public function testCategoryColorOrdonnances(): void
    {
        $this->assertSame('#8f5fff', category_color('Ordonnances'));
    }

    public function testCategoryColorRadios(): void
    {
        $this->assertSame('#1e90ff', category_color('Radios / Imagerie'));
    }

    public function testCategoryColorAnalyses(): void
    {
        $this->assertSame('#00c875', category_color('Analyses / Biologie'));
    }

    public function testCategoryColorVaccins(): void
    {
        $this->assertSame('#ff9800', category_color('Vaccins'));
    }

    public function testCategoryColorComptesRendus(): void
    {
        $this->assertSame('#e91e63', category_color('Comptes-rendus'));
    }

    public function testCategoryColorAutres(): void
    {
        $this->assertSame('#607d8b', category_color('Autres'));
    }

    public function testCategoryColorFallbackForUnknownCategory(): void
    {
        $color = category_color('CategorieInconnue');
        $this->assertSame('#607d8b', $color);
    }

    public function testCategoryColorFallbackForEmptyString(): void
    {
        $color = category_color('');
        $this->assertSame('#607d8b', $color);
    }

    public function testAllKnownCategoriesHaveColor(): void
    {
        $defaultColor = '#607d8b';
        $specificCategories = ['Ordonnances', 'Radios / Imagerie', 'Analyses / Biologie', 'Vaccins', 'Comptes-rendus'];
        foreach ($specificCategories as $cat) {
            $this->assertNotSame($defaultColor, category_color($cat), "'$cat' devrait avoir une couleur spécifique");
        }
    }
}
