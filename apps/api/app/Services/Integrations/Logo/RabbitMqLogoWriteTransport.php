<?php

namespace App\Services\Integrations\Logo;

use App\Services\Integrations\Logo\Contracts\LogoWriteTransport;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMqLogoWriteTransport implements LogoWriteTransport
{
    public function publish(array $envelope): void
    {
        $connection = new AMQPStreamConnection(
            host: (string) config('integrations.logo.write.rabbitmq.host'),
            port: (int) config('integrations.logo.write.rabbitmq.port'),
            user: (string) config('integrations.logo.write.rabbitmq.user'),
            password: (string) config('integrations.logo.write.rabbitmq.password'),
            vhost: (string) config('integrations.logo.write.rabbitmq.vhost'),
            insist: false,
            login_method: 'AMQPLAIN',
            login_response: null,
            locale: 'en_US',
            connection_timeout: (float) config('integrations.logo.write.rabbitmq.connection_timeout', 3.0),
            read_write_timeout: (float) config('integrations.logo.write.rabbitmq.read_write_timeout', 3.0),
            context: null,
            keepalive: false,
            heartbeat: (int) config('integrations.logo.write.rabbitmq.heartbeat', 30),
        );

        $channel = $connection->channel();

        try {
            $exchange = (string) ($envelope['exchange'] ?? config('integrations.logo.write.exchange', 'powersa.logo'));
            $routingKey = (string) ($envelope['routing_key'] ?? $envelope['event_type'] ?? '');

            $channel->exchange_declare(
                exchange: $exchange,
                type: 'topic',
                passive: false,
                durable: true,
                auto_delete: false
            );

            $payload = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $message = new AMQPMessage($payload, [
                'content_type' => 'application/json',
                'delivery_mode' => 2,
                'message_id' => (string) ($envelope['event_id'] ?? ''),
                'timestamp' => time(),
                'application_headers' => new AMQPTable([
                    'event_type' => (string) ($envelope['event_type'] ?? ''),
                    'idempotency_key' => (string) ($envelope['idempotency_key'] ?? ''),
                    'entity_type' => (string) ($envelope['entity_type'] ?? ''),
                    'entity_id' => (string) ($envelope['entity_id'] ?? ''),
                ]),
            ]);

            $channel->basic_publish($message, $exchange, $routingKey);
        } finally {
            $channel->close();
            $connection->close();
        }
    }
}
