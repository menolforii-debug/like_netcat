<?php

final class UsersListView
{
    public static function renderCard(array $users, array $roles, bool $hasRole, int $adminCount): void
    {
        echo '<div class="card-body">';
        echo '<h2 class="h6 mb-3">Список пользователей</h2>';

        if (empty($users)) {
            echo '<div class="alert alert-light border">Пользователи пока не созданы.</div>';
        } else {
            foreach ($users as $row) {
                $userId = (int) $row['id'];
                $userRole = $row['role'] ?? null;
                $isLastAdmin = $hasRole && $userRole === 'admin' && $adminCount <= 1;

                echo '<div class="border rounded p-3 mb-3">';
                echo '<div class="d-flex justify-content-between align-items-start mb-2">';
                echo '<div>';
                echo '<div class="fw-semibold">' . htmlspecialchars((string) $row['login'], ENT_QUOTES, 'UTF-8') . '</div>';
                if ($hasRole) {
                    echo '<div class="text-muted small">Роль: ' . htmlspecialchars((string) $userRole, ENT_QUOTES, 'UTF-8') . '</div>';
                }
                echo '</div>';
                echo '<div class="text-muted small">ID: ' . $userId . '</div>';
                echo '</div>';

                echo '<div class="row g-2">';
                if ($hasRole) {
                    echo '<div class="col-md-6">';
                    echo '<form method="post" action="/admin.php?action=user_update_role">';
                    echo csrf_token_field();
                    echo '<input type="hidden" name="user_id" value="' . $userId . '">';
                    echo '<label class="form-label">Роль</label>';
                    echo '<div class="input-group">';
                    echo '<select class="form-select" name="role" ' . ($isLastAdmin ? 'disabled' : '') . '>';
                    foreach ($roles as $value => $label) {
                        $selected = $value === $userRole ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    echo '</select>';
                    echo '<button class="btn btn-outline-primary" type="submit" ' . ($isLastAdmin ? 'disabled' : '') . '>Обновить</button>';
                    echo '</div>';
                    echo '</form>';
                    if ($isLastAdmin) {
                        echo '<div class="text-muted small mt-1">Нельзя изменить роль последнего администратора.</div>';
                    }
                    echo '</div>';
                }

                echo '<div class="col-md-6">';
                echo '<form method="post" action="/admin.php?action=user_update_password">';
                echo csrf_token_field();
                echo '<input type="hidden" name="user_id" value="' . $userId . '">';
                echo '<label class="form-label">Новый пароль</label>';
                echo '<div class="input-group">';
                echo '<input class="form-control" type="password" name="password" required>';
                echo '<button class="btn btn-outline-primary" type="submit">Сменить</button>';
                echo '</div>';
                echo '</form>';
                echo '</div>';
                echo '</div>';

                echo '<form method="post" action="/admin.php?action=user_delete" class="mt-3" data-confirm="Удалить пользователя?">';
                echo csrf_token_field();
                echo '<input type="hidden" name="user_id" value="' . $userId . '">';
                $disabledAttr = $isLastAdmin ? ' disabled' : '';
                echo '<button class="btn btn-outline-danger btn-sm"' . $disabledAttr . '>Удалить</button>';
                if ($isLastAdmin) {
                    echo '<div class="text-muted small mt-1">Нельзя удалить последнего администратора.</div>';
                }
                echo '</form>';

                echo '</div>';
            }
        }

        echo '</div>';
    }
}
