<?php

declare(strict_types=1);

namespace Drupal\Tests\support_tickets\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\support_tickets\Entity\Ticket;
use Drupal\support_tickets\Entity\TicketInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use GuzzleHttp\RequestOptions;

/**
 * Functional tests proving JSON:API rejects illegal status transitions.
 *
 * @group support_tickets
 */
class TicketStatusTransitionJsonApiTest extends BrowserTestBase {

  use JsonApiRequestTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'support_tickets',
    'basic_auth',
    'jsonapi',
    'serialization',
    'user',
    'views',
    'options',
    'field',
    'text',
    'filter',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * User with ticket permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $ticketUser;

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    // Defensive: Views export occasionally includes keys without schema.
    'views.view.support_tickets',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Allow JSON:API writes (Drupal defaults to read-only).
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save();

    $this->ticketUser = $this->drupalCreateUser([
      'access support tickets',
      'create support tickets',
      'edit support tickets',
      'add support ticket comments',
      'access content',
    ]);
  }

  /**
   * Creates an open ticket owned by the test user.
   */
  protected function createOpenTicket(): TicketInterface {
    $ticket = Ticket::create([
      'title' => 'JSON:API transition ticket',
      'description' => 'Used for API-level state machine tests.',
      'priority' => 'medium',
      'status' => TicketInterface::STATUS_OPEN,
      'created_by' => $this->ticketUser->id(),
    ]);
    $violations = $ticket->validate();
    $this->assertCount(0, $violations, (string) $violations);
    $ticket->save();
    return $ticket;
  }

  /**
   * PATCHes ticket status via JSON:API using basic auth.
   */
  protected function patchTicketStatus(TicketInterface $ticket, string $status): \Psr\Http\Message\ResponseInterface {
    $url = Url::fromUri('internal:/jsonapi/support_ticket/support_ticket/' . $ticket->uuid());
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::AUTH] = [
      $this->ticketUser->getAccountName(),
      $this->ticketUser->pass_raw,
    ];
    $request_options[RequestOptions::JSON] = [
      'data' => [
        'type' => 'support_ticket--support_ticket',
        'id' => $ticket->uuid(),
        'attributes' => [
          'status' => $status,
        ],
      ],
    ];
    // JsonApiRequestTestTrait expects body as string for some paths; use body.
    $request_options[RequestOptions::BODY] = Json::encode($request_options[RequestOptions::JSON]);
    unset($request_options[RequestOptions::JSON]);

    return $this->request('PATCH', $url, $request_options);
  }

  /**
   * Reloads a ticket from storage, bypassing static cache.
   */
  protected function reloadTicket(int|string $id): TicketInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('support_ticket');
    $storage->resetCache([(string) $id]);
    $ticket = $storage->load($id);
    $this->assertInstanceOf(TicketInterface::class, $ticket);
    return $ticket;
  }

  /**
   * Illegal Open -> Resolved must be rejected at the API layer.
   */
  public function testJsonApiRejectsInvalidOpenToResolved(): void {
    $ticket = $this->createOpenTicket();
    $response = $this->patchTicketStatus($ticket, 'resolved');

    $this->assertSame(422, $response->getStatusCode(), (string) $response->getBody());
    $document = Json::decode((string) $response->getBody());
    $this->assertNotEmpty($document['errors'] ?? []);
    $detail = $document['errors'][0]['detail'] ?? '';
    $this->assertStringContainsStringIgnoringCase('transition', $detail);

    $reloaded = $this->reloadTicket($ticket->id());
    $this->assertSame('open', $reloaded->getStatusValue());
  }

  /**
   * Legal Open -> In Progress must succeed via JSON:API.
   */
  public function testJsonApiAllowsValidOpenToInProgress(): void {
    $ticket = $this->createOpenTicket();
    $response = $this->patchTicketStatus($ticket, 'in_progress');

    $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    $reloaded = $this->reloadTicket($ticket->id());
    $this->assertSame('in_progress', $reloaded->getStatusValue());
  }

}
