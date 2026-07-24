<?php

declare(strict_types=1);

namespace Drupal\Tests\support_tickets\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\support_tickets\Entity\Ticket;
use Drupal\support_tickets\Entity\TicketInterface;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the ticket status state machine.
 *
 * @group support_tickets
 */
class TicketStatusTransitionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'options',
    'serialization',
    'support_tickets',
  ];

  /**
   * A user used as ticket owner.
   */
  protected User $owner;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('support_ticket');
    $this->installEntitySchema('support_ticket_comment');
    $this->installConfig(['system', 'user', 'filter']);

    $this->owner = User::create([
      'name' => 'ticket_owner',
      'status' => 1,
    ]);
    $this->owner->save();
  }

  /**
   * Creates and saves a new open ticket.
   */
  protected function createOpenTicket(string $title = 'Kernel test ticket'): TicketInterface {
    $ticket = Ticket::create([
      'title' => $title,
      'description' => 'Description for kernel test.',
      'priority' => 'medium',
      'status' => TicketInterface::STATUS_OPEN,
      'created_by' => $this->owner->id(),
    ]);
    $violations = $ticket->validate();
    $this->assertCount(0, $violations, (string) $violations);
    $ticket->save();
    return $ticket;
  }

  /**
   * Applies a status and asserts validation + save succeed.
   */
  protected function assertTransitionSucceeds(TicketInterface $ticket, string $to): void {
    $ticket->setStatusValue($to);
    $violations = $ticket->validate();
    $this->assertCount(0, $violations, sprintf(
      'Expected transition to %s to succeed; got: %s',
      $to,
      (string) $violations
    ));
    $ticket->save();
    $reloaded = Ticket::load($ticket->id());
    $this->assertSame($to, $reloaded->getStatusValue());
  }

  /**
   * Applies a status and asserts validation fails with a clear message.
   */
  protected function assertTransitionRejected(TicketInterface $ticket, string $to): void {
    $from = $ticket->getStatusValue();
    $ticket->setStatusValue($to);
    $violations = $ticket->validate();
    $this->assertGreaterThan(0, $violations->count(), sprintf(
      'Expected transition %s -> %s to be rejected.',
      $from,
      $to
    ));

    $messages = [];
    foreach ($violations as $violation) {
      $messages[] = (string) $violation->getMessage();
    }
    $combined = implode(' ', $messages);
    $this->assertStringContainsStringIgnoringCase('transition', $combined);
    $this->assertTrue(
      str_contains($combined, 'Invalid') || str_contains($combined, 'must start'),
      'Violation message should be user-facing: ' . $combined
    );

    // Persist must not keep the illegal status if we refuse to save on violations.
    // Reload from DB to prove storage was not updated by a failed validate-only call.
    $reloaded = Ticket::load($ticket->id());
    $this->assertSame($from, $reloaded->getStatusValue());
  }

  /**
   * @covers ::validate
   */
  public function testValidOpenToInProgress(): void {
    $ticket = $this->createOpenTicket();
    $this->assertTransitionSucceeds($ticket, 'in_progress');
  }

  /**
   * @covers ::validate
   */
  public function testValidOpenToCancelled(): void {
    $ticket = $this->createOpenTicket('Cancel me');
    $this->assertTransitionSucceeds($ticket, 'cancelled');
  }

  /**
   * @covers ::validate
   */
  public function testValidInProgressToResolved(): void {
    $ticket = $this->createOpenTicket('Resolve me');
    $this->assertTransitionSucceeds($ticket, 'in_progress');
    $this->assertTransitionSucceeds($ticket, 'resolved');
  }

  /**
   * @covers ::validate
   */
  public function testValidInProgressToCancelled(): void {
    $ticket = $this->createOpenTicket('Cancel in progress');
    $this->assertTransitionSucceeds($ticket, 'in_progress');
    $this->assertTransitionSucceeds($ticket, 'cancelled');
  }

  /**
   * @covers ::validate
   */
  public function testValidResolvedToClosed(): void {
    $ticket = $this->createOpenTicket('Close me');
    $this->assertTransitionSucceeds($ticket, 'in_progress');
    $this->assertTransitionSucceeds($ticket, 'resolved');
    $this->assertTransitionSucceeds($ticket, 'closed');
  }

  /**
   * Same-status save is a no-op and must be allowed.
   */
  public function testSameStatusNoOpAllowed(): void {
    $ticket = $this->createOpenTicket('No op');
    $this->assertTransitionSucceeds($ticket, 'open');
  }

  /**
   * Invalid: Open -> Resolved (must go through in_progress).
   */
  public function testInvalidOpenToResolved(): void {
    $ticket = $this->createOpenTicket('Bad open to resolved');
    $this->assertTransitionRejected($ticket, 'resolved');
  }

  /**
   * Invalid: Closed -> Open (terminal).
   */
  public function testInvalidClosedToOpen(): void {
    $ticket = $this->createOpenTicket('Bad closed to open');
    $this->assertTransitionSucceeds($ticket, 'in_progress');
    $this->assertTransitionSucceeds($ticket, 'resolved');
    $this->assertTransitionSucceeds($ticket, 'closed');
    $this->assertTransitionRejected($ticket, 'open');
  }

  /**
   * Invalid: Cancelled -> In Progress (terminal).
   */
  public function testInvalidCancelledToInProgress(): void {
    $ticket = $this->createOpenTicket('Bad cancelled to in progress');
    $this->assertTransitionSucceeds($ticket, 'cancelled');
    $this->assertTransitionRejected($ticket, 'in_progress');
  }

  /**
   * New tickets may not be created directly in a non-open status.
   */
  public function testNewTicketMustStartOpen(): void {
    $ticket = Ticket::create([
      'title' => 'Illegal initial status',
      'description' => 'Should not start resolved.',
      'priority' => 'low',
      'status' => 'resolved',
      'created_by' => $this->owner->id(),
    ]);
    $violations = $ticket->validate();
    $this->assertGreaterThan(0, $violations->count());
    $this->assertStringContainsStringIgnoringCase('open', (string) $violations);
  }

}
