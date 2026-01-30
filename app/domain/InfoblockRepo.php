<?php

final class InfoblockRepo
{
    public function listForSection($sectionId, bool $onlyEnabled = false): array
    {
        $where = 'section_id = :section_id';
        if ($onlyEnabled) {
            $where .= ' AND is_enabled = 1';
        }

        return DB::fetchAll(
            'SELECT id, site_id, section_id, component_id, `key`, name, view_template, per_page, extra_json, sort, is_enabled
            FROM infoblocks
            WHERE ' . $where . '
            ORDER BY sort ASC, id ASC',
            ['section_id' => $sectionId]
        );
    }

    public function findById(int $id): ?array
    {
        return DB::fetchOne(
            'SELECT id, site_id, section_id, component_id, `key`, name, view_template, per_page, extra_json, sort, is_enabled
            FROM infoblocks
            WHERE id = :id
            LIMIT 1',
            ['id' => $id]
        );
    }

    public function create(array $data): int
    {
        $stmt = DB::pdo()->prepare(
            'INSERT INTO infoblocks (site_id, section_id, component_id, `key`, name, view_template, per_page, extra_json, sort, is_enabled)
            VALUES (:site_id, :section_id, :component_id, :key, :name, :view_template, :per_page, :extra_json, :sort, :is_enabled)'
        );
        $stmt->execute([
            'site_id' => $data['site_id'],
            'section_id' => $data['section_id'],
            'component_id' => $data['component_id'],
            'key' => $data['key'] ?? '',
            'name' => $data['name'],
            'view_template' => $data['view_template'],
            'per_page' => $data['per_page'] ?? 0,
            'extra_json' => json_encode($data['extra'] ?? [], JSON_UNESCAPED_UNICODE),
            'sort' => $data['sort'] ?? 0,
            'is_enabled' => $data['is_enabled'] ?? 1,
        ]);

        $id = (int) DB::pdo()->lastInsertId();
        core()->events()->emit('infoblock.created', [
            'id' => $id,
            'data' => $data,
        ]);

        return $id;
    }

    public function update($id, array $data): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE infoblocks
            SET `key` = :key, name = :name, view_template = :view_template, per_page = :per_page, extra_json = :extra_json, sort = :sort, is_enabled = :is_enabled
            WHERE id = :id'
        );
        $stmt->execute([
            'key' => $data['key'] ?? '',
            'name' => $data['name'],
            'view_template' => $data['view_template'],
            'per_page' => $data['per_page'] ?? 0,
            'extra_json' => json_encode($data['extra'] ?? [], JSON_UNESCAPED_UNICODE),
            'sort' => $data['sort'] ?? 0,
            'is_enabled' => $data['is_enabled'] ?? 1,
            'id' => $id,
        ]);

        core()->events()->emit('infoblock.updated', [
            'id' => $id,
            'data' => $data,
        ]);
    }

    public function delete($id): void
    {
        $stmt = DB::pdo()->prepare('DELETE FROM infoblocks WHERE id = :id');
        $stmt->execute(['id' => $id]);

        core()->events()->emit('infoblock.deleted', ['id' => $id]);
    }

    public function deleteRecursive(int $id, ?int $expectedSectionId = null): void
    {
        $infoblock = $this->findById($id);
        if ($infoblock === null) {
            throw new RuntimeException('Инфоблок не найден.');
        }

        if ($expectedSectionId !== null && (int) $infoblock['section_id'] !== (int) $expectedSectionId) {
            throw new RuntimeException('Инфоблок не принадлежит указанному разделу.');
        }

        $pdo = DB::pdo();
        $manageTransaction = !$pdo->inTransaction();
        if ($manageTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM objects WHERE infoblock_id = :id');
            $stmt->execute(['id' => $id]);

            $stmt = $pdo->prepare('DELETE FROM infoblocks WHERE id = :id');
            $stmt->execute(['id' => $id]);

            if ($manageTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($manageTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        core()->events()->emit('infoblock.deleted', ['id' => $id]);
    }
}
