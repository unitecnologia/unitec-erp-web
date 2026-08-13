<?php

namespace App\Support\Erp\Reports\Tabular;

use Illuminate\Http\Request;

interface TabularReportDefinition
{
    public function slug(): string;

    public function title(): string;

    public function permission(): string;

    public function filename(): string;

    /**
     * @return array<string, string>
     */
    public function columns(): array;

    /**
     * @return list<string>
     */
    public function defaultColumns(): array;

    /**
     * @return list<string>
     */
    public function numericColumns(): array;

    /**
     * @return list<array{key: string, label: string, type: string, options?: array<string, string>}>
     */
    public function filterFields(): array;

    /**
     * @return array{
     *     rows: list<array<string, string>>,
     *     totals: array<string, string>,
     *     summary: list<string>,
     *     filters: array<string, mixed>,
     *     columns: list<string>
     * }
     */
    public function build(Request $request): array;
}
