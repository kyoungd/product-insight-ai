CREATE TABLE wp_product_insight_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    api_key VARCHAR(64) NOT NULL DEFAUT '',
    customer_domain VARCHAR(255) NOT NULL DEFAULT '',
    stripe_customer_id VARCHAR(255) NOT NULL DEFAULT '',,
    stripe_subscription_id VARCHAR(255) NOT NULL DEFAULT '',,
    stripe_price_id VARCHAR(255) NOT NULL DEFAULT '',,
    subscription_status VARCHAR(32) NOT NULL DEFAULT 'active',
    subscription_plan VARCHAR(255) NOT NULL DEFAULT 'free',
    plugin_type VARCHAR(255) NOT NULL DEFAULT '',,
    query_count INT DEFAULT 0,
    query_limit INT DEFAULT 300,
    billing_period_start DATE = CURRENT_DATE,
    billing_period_end DATE = CURRENT_DATE + INTERVAL 1 MONTH,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES wp_users(ID) ON DELETE CASCADE,
    UNIQUE KEY unique_customer_domain (customer_id, customer_domain),
);


This table exists.

if domain of the calling URL does not match the domain of the api key, return a 403 error.
if query_count is greater than query_limit, return a 403 error.
if subscription_status is not 'active', return a 403 error.
if billing_period_end is greater than current date, return a 403 error.

otherwise, submit the call and increment query_count by 1.


TEST API KEY
8a9a8713-dfeb
lcoalhost

