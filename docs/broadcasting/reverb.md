# Broadcasting with Laravel Reverb

This project uses event broadcasting for real-time communication, with
**Laravel Reverb** as its primary implementation. Reverb is becoming a
standard solution in the Laravel ecosystem.

## Broadcasting principles

WebSocket broadcasting lets the server send information to clients as soon as
it becomes available.

- **Efficiency:** It removes the need for the client to constantly poll the
  server for updates.
- **Immediacy:** Clients are informed almost immediately when data changes,
  which is important for collaborative applications and live data streams.
- **Autonomy:** Reverb avoids dependence on paid third-party services such as
  Pusher or Ably while keeping infrastructure under project control.

## Use case: client-cache invalidation

Consider a frontend that caches a product list in IndexedDB, localStorage, or a
more advanced store such as SQLite WASM with OPFS.

**Problem:** How can the cache remain current when a product changes in the
database?

**Broadcasting solution:**

1. When a product is created, updated, or deleted, the backend broadcasts a
   private event such as `ProductChanged`.
2. The frontend listens for this event and can react in several ways:
   - **Full refresh:** The event tells the client that data changed, so it can
     clear its local cache and request the full list on the next API call.
   - **Targeted update:** The event contains the changed product, for example
     through `client_refresh`. The frontend updates, adds, or removes that
     item in its local cache without reloading everything.
   - **URL notification:** The event sends a specific URL, such as
     `/api/products/123`, to refresh. The frontend then knows which data is
     stale.

This approach enables fast, reactive interfaces while optimizing network calls
and preserving data consistency.
