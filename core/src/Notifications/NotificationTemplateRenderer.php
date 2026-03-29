<?php

namespace PymeSec\Core\Notifications;

class NotificationTemplateRenderer
{
    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $metadata
     * @return array{title: string, body: string}
     */
    public function render(
        array $template,
        string $notificationType,
        string $title,
        string $body,
        ?string $principalId,
        ?string $organizationId,
        ?string $scopeId,
        ?string $deliverAt,
        array $metadata,
    ): array {
        $variables = [
            'notification_type' => $notificationType,
            'notification_title' => $title,
            'notification_body' => $body,
            'principal_id' => $principalId ?? '',
            'organization_id' => $organizationId ?? '',
            'scope_id' => $scopeId ?? '',
            'deliver_at' => $deliverAt ?? '',
        ];

        foreach ($metadata as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $variables[$key] = $value === null ? '' : (string) $value;
            }
        }

        $renderedTitle = $this->renderString((string) ($template['title_template'] ?? ''), $variables);
        $renderedBody = $this->renderString((string) ($template['body_template'] ?? ''), $variables);

        return [
            'title' => $renderedTitle !== '' ? $renderedTitle : $title,
            'body' => $renderedBody !== '' ? $renderedBody : $body,
        ];
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function renderString(string $template, array $variables): string
    {
        if (trim($template) === '') {
            return '';
        }

        $rendered = preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', static function (array $matches) use ($variables): string {
            $key = is_string($matches[1] ?? null) ? $matches[1] : '';

            return $variables[$key] ?? '';
        }, $template);

        return is_string($rendered) ? trim($rendered) : '';
    }
}
