# Docker and VM runtime

Local development will use Docker containers for:

- WordPress/PHP with WooCommerce and project code.
- MariaDB.
- Redis.
- Mailpit for email preview and delivery testing.
- WP-CLI for repeatable setup and maintenance.

Product images will use Neviri Object Storage through its S3-compatible endpoint rather than a local MinIO container.

For initial deployment, WordPress/PHP and Redis can run on the same backend VM. MariaDB may also run there for the first controlled release, but the containers and volumes will stay separate so the database or Redis can move to managed services later without changing application code.

Mailpit is a free, open-source local testing service. It catches emails in development and provides a browser inbox. It is not a framework and is not used to deliver production email; production will use the SMTP provider configured through environment variables.
