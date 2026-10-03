<?php

namespace Tests\Unit;

use App\Domain\Platform\Services\Analytics\Reports\SqlJson;
use PHPUnit\Framework\TestCase;

/**
 * **El JSON de los informes, con la forma que el HOSTING sabe agrupar** (`SqlJson`, 03-10). El cuadro daba error 1055
 * en staging —MariaDB 11.4 con `ONLY_FULL_GROUP_BY`— porque agrupaba por un `NULLIF` y un `CASE`, que MariaDB no casa
 * con los del SELECT (medido allí: un `IF` repetido pasa, un `NULLIF` o un `CASE` repetidos fallan). La suite corre en
 * SQLite y nunca ve esa forma: por eso el motor va por parámetro y aquí se prueba la de MySQL/MariaDB.
 */
class AnalyticsSqlJsonTest extends TestCase
{
    public function test_on_the_hosting_the_json_text_is_an_if_never_a_nullif_or_a_case(): void
    {
        foreach (['mysql', 'mariadb'] as $motor) {
            $sql = SqlJson::string('props', '$.method', $motor);

            $this->assertSame("IF(JSON_UNQUOTE(JSON_EXTRACT(props, '$.method')) = 'null', NULL, JSON_UNQUOTE(JSON_EXTRACT(props, '$.method')))", $sql, $motor);
            $this->assertStringNotContainsString('NULLIF', $sql, $motor);
            $this->assertStringNotContainsString('CASE', $sql, $motor);
        }
    }

    public function test_present_is_an_if_on_the_hosting_and_a_case_only_on_sqlite(): void
    {
        $hosting = SqlJson::present('click_ids', '$.gclid', 'mysql');
        $this->assertStringStartsWith('IF(IF(JSON_UNQUOTE(JSON_EXTRACT(click_ids, ', $hosting);
        $this->assertStringEndsWith(' IS NULL, 0, 1)', $hosting);
        $this->assertStringNotContainsString('CASE', $hosting);
        $this->assertStringNotContainsString('NULLIF', $hosting);

        // SQLite no tiene `IF`: allí, el `CASE` (y agrupa sin problema).
        $this->assertSame("CASE WHEN json_extract(click_ids, '$.gclid') IS NULL THEN 0 ELSE 1 END", SqlJson::present('click_ids', '$.gclid', 'sqlite'));
    }
}
