<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * No RefreshDatabase here on purpose: this test issues DDL, and MySQL implicitly
 * commits DDL, which would break a wrapping transaction. The bespoke tables are
 * therefore dropped explicitly in `finally`, even if an assertion fails.
 */
test('InnoDB enforces foreign key constraints', function () {
    $parent = 'fpp_fk_parent';
    $child = 'fpp_fk_child';

    try {
        DB::statement("DROP TABLE IF EXISTS `{$child}`");
        DB::statement("DROP TABLE IF EXISTS `{$parent}`");

        DB::statement("CREATE TABLE `{$parent}` (
            id BIGINT UNSIGNED NOT NULL PRIMARY KEY
        ) ENGINE=InnoDB");

        DB::statement("CREATE TABLE `{$child}` (
            id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            parent_id BIGINT UNSIGNED NOT NULL,
            CONSTRAINT `fpp_fk_child_parent` FOREIGN KEY (parent_id)
                REFERENCES `{$parent}` (id)
        ) ENGINE=InnoDB");

        DB::table($parent)->insert(['id' => 1]);

        // A valid reference is accepted.
        DB::table($child)->insert(['id' => 1, 'parent_id' => 1]);
        expect(DB::table($child)->count())->toBe(1);

        // A dangling reference is rejected by the engine.
        expect(fn () => DB::table($child)->insert(['id' => 2, 'parent_id' => 999999]))
            ->toThrow(QueryException::class);
    } finally {
        DB::statement("DROP TABLE IF EXISTS `{$child}`");
        DB::statement("DROP TABLE IF EXISTS `{$parent}`");
    }
});
