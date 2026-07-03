<?php

namespace App\Console\Commands;

use App\Models\EmployeeDirectoryEntry;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportNotionDirectory extends Command
{
    protected $signature = 'portal:import-notion-directory
        {--limit=1000 : Maximum rows to request from Notion}
        {--dry-run : Read and parse Notion without writing to the database}
        {--no-verify-ssl : Disable SSL verification for Notion HTTP requests}';

    protected $description = 'Import WDC Information Directory records from the public Notion database.';

    private const HOST = 'https://talented-vulcanodon-8da.notion.site';
    private const PAGE_ID = 'be7362d6-8d2c-4848-8559-110e03ceea13';
    private const VIEW_ID = 'd8ef55a6-b0cc-49ea-bad1-8939e44be97f';
    private const SPACE_ID = '6012f614-8715-42af-a9cc-5980a5f8632b';

    public function handle(): int
    {
        $pagePayload = $this->loadPage();
        $rootBlock = $this->recordValue(data_get($pagePayload, 'recordMap.block.'.self::PAGE_ID));
        $collectionId = $rootBlock['collection_id'] ?? null;

        if (! $collectionId) {
            $this->error('Cannot find Notion collection id.');

            return self::FAILURE;
        }

        $collection = $this->recordValue(data_get($pagePayload, "recordMap.collection.{$collectionId}"));
        $schema = $collection['schema'] ?? [];

        if ($schema === []) {
            $this->error('Cannot find Notion collection schema.');

            return self::FAILURE;
        }

        $queryPayload = $this->queryCollection($collectionId, (int) $this->option('limit'));
        $blockIds = data_get($queryPayload, 'result.reducerResults.collection_group_results.blockIds', []);
        $blocks = data_get($queryPayload, 'recordMap.block', []);

        $rows = collect($blockIds)
            ->map(fn (string $blockId) => $this->recordValue($blocks[$blockId] ?? null))
            ->filter()
            ->map(fn (array $block) => $this->mapDirectoryRow($block, $blocks, $schema))
            ->filter(fn (array $row) => $row['display_name'] !== '')
            ->values();

        $stats = $rows->countBy('entry_type');
        $this->info("Parsed {$rows->count()} Notion directory rows.");
        $this->line('Employees: '.($stats['employee'] ?? 0));
        $this->line('Mail groups: '.($stats['mail_group'] ?? 0));
        $this->line('Showrooms: '.($stats['showroom'] ?? 0));

        if ($this->option('dry-run')) {
            $this->warn('Dry run only. No rows were written.');

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $entry = EmployeeDirectoryEntry::where('source_system', 'notion')
                ->where('source_record_id', $row['source_record_id'])
                ->first();

            if (! $entry) {
                $entry = $this->findExistingEntry($row);
            }

            if ($entry) {
                $entry->fill($row)->save();
                $updated++;

                continue;
            }

            EmployeeDirectoryEntry::create($row);
            $created++;
        }

        $this->info("Imported {$rows->count()} rows. Created {$created}, updated {$updated}.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadPage(): array
    {
        return $this->notionHttp(30)
            ->asJson()
            ->post('https://www.notion.so/api/v3/loadCachedPageChunk', [
                'pageId' => self::PAGE_ID,
                'limit' => 100,
                'cursor' => ['stack' => []],
                'chunkNumber' => 0,
                'verticalColumns' => false,
            ])
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function queryCollection(string $collectionId, int $limit): array
    {
        return $this->notionHttp(45)
            ->asJson()
            ->post('https://www.notion.so/api/v3/queryCollection', [
                'collection' => [
                    'id' => $collectionId,
                    'spaceId' => self::SPACE_ID,
                ],
                'collectionView' => [
                    'id' => self::VIEW_ID,
                    'spaceId' => self::SPACE_ID,
                ],
                'loader' => [
                    'type' => 'reducer',
                    'reducers' => [
                        'collection_group_results' => [
                            'type' => 'results',
                            'limit' => $limit,
                            'loadContentCover' => true,
                        ],
                    ],
                    'searchQuery' => '',
                    'userTimeZone' => 'Asia/Bangkok',
                ],
            ])
            ->throw()
            ->json();
    }

    private function notionHttp(int $timeout): PendingRequest
    {
        $request = Http::timeout($timeout)->retry(2, 250);

        if ($this->option('no-verify-ssl') || ! filter_var(env('NOTION_IMPORT_VERIFY_SSL', true), FILTER_VALIDATE_BOOL)) {
            $request = $request->withOptions(['verify' => false]);
        }

        return $request;
    }

    /**
     * @param array<string, mixed>|null $record
     * @return array<string, mixed>|null
     */
    private function recordValue(?array $record): ?array
    {
        $value = $record['value'] ?? null;

        if (isset($value['value']) && is_array($value['value'])) {
            return $value['value'];
        }

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $block
     * @param array<string, mixed> $blocks
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function mapDirectoryRow(array $block, array $blocks, array $schema): array
    {
        $displayName = $this->property($block, $schema, 'Name') ?: $this->plainText(data_get($block, 'properties.title'));
        $thaiName = $this->property($block, $schema, 'ชื่อภาษาไทย');
        $department = $this->property($block, $schema, 'Department');
        $team = $this->property($block, $schema, 'Team');
        $position = $this->property($block, $schema, 'Job Title');
        $englishNickname = $this->property($block, $schema, 'English Nickname')
            ?: $this->property($block, $schema, 'Nickname EN')
            ?: $this->property($block, $schema, 'ชื่อเล่นอังกฤษ');
        $thaiNickname = $this->property($block, $schema, 'Thai Nickname')
            ?: $this->property($block, $schema, 'ชื่อเล่น')
            ?: $this->property($block, $schema, 'Nickname TH')
            ?: $this->property($block, $schema, 'Nickname');
        $nickname = $thaiNickname ?: $englishNickname;
        $location = $this->property($block, $schema, 'Location');
        $email = $this->property($block, $schema, 'Email');
        $extensionNumber = $this->property($block, $schema, 'Ext.');
        $entryType = $this->entryType($displayName, $department, $position);
        $phone = null;
        $notes = null;

        if ($entryType === 'mail_group' && $email === '' && str_contains($displayName, '@')) {
            $email = $displayName;
        }

        if ($entryType === 'showroom') {
            $phone = $this->extractPhone($position);
            $notes = $thaiName !== '' ? $thaiName : null;
            $thaiName = null;
        }

        if ($team !== '' && $entryType === 'employee') {
            $notes = $notes ? "{$notes}\nTeam: {$team}" : "Team: {$team}";
        }

        $row = [
            'source_system' => 'notion',
            'source_record_id' => $block['id'],
            'source_url' => self::HOST.'/'.str_replace('-', '', $block['id']),
            'image_url' => $this->imageUrl($block, $blocks),
            'entry_type' => $entryType,
            'display_name' => $this->clean($displayName),
            'english_name' => $entryType === 'employee' ? $this->clean($displayName) : null,
            'thai_name' => $this->nullableClean($thaiName),
            'nickname' => $this->nullableClean($nickname),
            'department' => $this->nullableClean($department),
            'team' => $this->nullableClean($team),
            'position' => $this->nullableClean($position),
            'location' => $this->nullableClean($location),
            'email' => $this->nullableClean($email),
            'phone' => $this->nullableClean($phone),
            'extension_number' => $this->nullableClean($extensionNumber),
            'notes' => $this->nullableClean($notes),
            'raw_payload' => [
                'notion_id' => $block['id'],
                'properties' => $block['properties'] ?? [],
                'content' => $block['content'] ?? [],
            ],
            'imported_at' => now(),
            'is_active' => true,
        ];

        if (Schema::hasColumn('employee_directory_entries', 'english_nickname')) {
            $row['english_nickname'] = $this->nullableClean($englishNickname);
        }

        if (Schema::hasColumn('employee_directory_entries', 'thai_nickname')) {
            $row['thai_nickname'] = $this->nullableClean($thaiNickname);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $block
     * @param array<string, mixed> $schema
     */
    private function property(array $block, array $schema, string $propertyName): string
    {
        $id = collect($schema)
            ->search(fn (array $definition) => ($definition['name'] ?? null) === $propertyName);

        return is_string($id) ? $this->plainText(data_get($block, "properties.{$id}")) : '';
    }

    /**
     * @param mixed $value
     */
    private function plainText($value): string
    {
        if (! is_array($value)) {
            return '';
        }

        $text = collect($value)
            ->map(fn ($part) => is_array($part) ? ($part[0] ?? '') : '')
            ->implode('');

        return Str::of($text)
            ->squish()
            ->toString();
    }

    private function entryType(string $displayName, string $department, string $position): string
    {
        if ($department === 'Mail Group' || str_contains($displayName, '@')) {
            return 'mail_group';
        }

        if ($department === 'Showroom' || Str::contains(Str::lower($displayName.' '.$position), 'showroom')) {
            return 'showroom';
        }

        return 'employee';
    }

    private function imageUrl(array $block, array $blocks): ?string
    {
        foreach (($block['content'] ?? []) as $childId) {
            $child = $this->recordValue($blocks[$childId] ?? null);

            if (($child['type'] ?? null) !== 'image') {
                continue;
            }

            $source = data_get($child, 'format.display_source')
                ?: data_get($child, 'format.original_source')
                ?: $this->plainText(data_get($child, 'properties.source'));

            if (! is_string($source) || $source === '' || str_starts_with($source, '/images/page-cover')) {
                continue;
            }

            return self::HOST.'/image/'.rawurlencode($source)
                .'?table=block&id='.$child['id']
                .'&spaceId='.($child['space_id'] ?? self::SPACE_ID)
                .'&width=520&userId=&cache=v2&imgBuildSrc=requestProxiedImageUrl';
        }

        return null;
    }

    private function extractPhone(?string $value): ?string
    {
        if (! $value || ! str_contains($value, 'Tel:')) {
            return null;
        }

        return trim(Str::after($value, 'Tel:')) ?: null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function findExistingEntry(array $row): ?EmployeeDirectoryEntry
    {
        $query = EmployeeDirectoryEntry::where('source_system', 'notion')
            ->whereNull('source_record_id');

        if ($row['email']) {
            $match = (clone $query)->where('email', $row['email'])->first();

            if ($match) {
                return $match;
            }
        }

        return $query->where('display_name', $row['display_name'])->first();
    }

    private function nullableClean(?string $value): ?string
    {
        $clean = $this->clean($value ?? '');

        return $clean === '' ? null : $clean;
    }

    private function clean(string $value): string
    {
        return Str::of($value)->replaceMatches('/\s+/u', ' ')->trim()->toString();
    }
}
