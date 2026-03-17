<?php
declare(strict_types=1);

namespace CakeHx\Controller\Component;

use Cake\Controller\Component;
use Cake\Event\EventInterface;

/**
 * HxComponent — server-side helper for handling CakeHx requests.
 *
 * Automatically detects HX (AJAX partial) requests and:
 *   - Disables the layout so only the template fragment is returned.
 *   - Provides helper methods to inspect request metadata.
 *
 * Usage in a Controller:
 *   public function initialize(): void {
 *       $this->loadComponent('CakeHx.Hx');
 *   }
 *
 *   public function search() {
 *       if ($this->Hx->isHxRequest()) {
 *           // only render the results partial
 *           $this->viewBuilder()->setTemplate('search_results');
 *       }
 *   }
 */
class HxComponent extends Component
{
    /**
     * Default config.
     *
     * - autoNoLayout: automatically disable layout for HX requests (default true)
     */
    protected array $_defaultConfig = [
        'autoNoLayout' => true,
    ];

    /**
     * Is this an HX (partial AJAX) request?
     */
    public function isHxRequest(): bool
    {
        return $this->getController()->getRequest()->getHeaderLine('X-HX-Request') === 'true';
    }

    /**
     * Return the trigger event name sent by the client.
     */
    public function getTrigger(): ?string
    {
        $val = $this->getController()->getRequest()->getHeaderLine('X-HX-Trigger');

        return $val !== '' ? $val : null;
    }

    /**
     * Return the target element ID sent by the client.
     */
    public function getTarget(): ?string
    {
        $val = $this->getController()->getRequest()->getHeaderLine('X-HX-Target');

        return $val !== '' ? $val : null;
    }

    /**
     * Return the current URL the client was on when the request was made.
     */
    public function getCurrentUrl(): ?string
    {
        $val = $this->getController()->getRequest()->getHeaderLine('X-HX-Current-URL');

        return $val !== '' ? $val : null;
    }

    /**
     * beforeRender — automatically strips layout for HX requests.
     */
    public function beforeRender(EventInterface $event): void
    {
        if ($this->getConfig('autoNoLayout') && $this->isHxRequest()) {
            $this->getController()->viewBuilder()->disableAutoLayout();
        }
    }

    /**
     * Set a response header that tells the client to trigger a client-side event.
     * Useful for triggering UI updates after a server action.
     *
     * @param string|array $events Event name or associative array of events with detail data
     */
    public function triggerClientEvent(string|array $events): void
    {
        $value = is_array($events) ? json_encode($events) : $events;
        $response = $this->getController()->getResponse()
            ->withHeader('X-HX-Trigger-After-Swap', $value);
        $this->getController()->setResponse($response);
    }

    /**
     * Tell the client to redirect to a different URL (full page load).
     */
    public function redirect(string $url): void
    {
        $response = $this->getController()->getResponse()
            ->withHeader('X-HX-Redirect', $url);
        $this->getController()->setResponse($response);
    }

    /**
     * Tell the client to refresh the whole page.
     */
    public function refresh(): void
    {
        $response = $this->getController()->getResponse()
            ->withHeader('X-HX-Refresh', 'true');
        $this->getController()->setResponse($response);
    }
}
