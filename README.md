# Wordpress - Geo Blocker Plugin

WordPress plugin for blocking visitors from specific countries using WP VIP geo-targeting. Lightweight, performant, and built for security monitoring.

## ⚡ Quick Start

1. **Install required dependency:** [VIP Geo Uniques](https://github.com/Automattic/vip-go-geo-uniques)
2. Upload this plugin to `/wp-content/plugins/`
3. Activate both plugins
4. View stats inside wp admin at **Tools → Geo Blocks**

## 🎯 Features

- Block visitors by country (China, Russia, Singapore, Brazil by default)
- Real-time dashboard with blocking statistics
- 30-day detailed tracking + 12-month automatic archiving
- Activity log (last 200 requests) with IP search
- Attack pattern analysis
- Custom 403 block page

## 📋 Requirements

- **WordPress VIP hosting** (required)
- **[VIP Geo Uniques plugin](https://github.com/Automattic/vip-go-geo-uniques)** (required)
- WordPress 5.0+
- PHP 7.4+

---
<img width="1688" height="574" alt="Screenshot 2025-10-22 at 11 46 32 PM" src="https://github.com/user-attachments/assets/5d3bd5ef-7131-4504-a06b-e8980e42f3c4" />
<img width="1352" height="994" alt="Screenshot 2025-10-22 at 11 47 02 PM" src="https://github.com/user-attachments/assets/62743539-74ae-46f2-b63d-fa7b91f90695" />
<img width="1400" height="486" alt="Screenshot 2025-10-22 at 11 47 56 PM" src="https://github.com/user-attachments/assets/5b91dba5-291c-45a9-bb6a-16b79376cd29" />


## ⚙️ Configuration

Add tracked locations in `geo-blocker-config.php`:
```php
VIP_Go_Geo_Uniques::add_location( 'RU' ); // Russia
VIP_Go_Geo_Uniques::add_location( 'SG' ); // Singapore
VIP_Go_Geo_Uniques::add_location( 'BR' ); // Brazil
```

Add tracked locations in `geo-blocker-plugin.php`:
```php
define( 'LOADUP_BLOCKED_COUNTRIES', 'CN,RU,SG,BR' );
```

Use [ISO country codes](https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2) (CN, RU, US, etc.)

Customize the block page at `templates/blocked-page.php`

## 📊 Dashboard Widgets

- **Today vs Yesterday** - Daily comparison with trend indicator
- **Daily Average** - 30-day rolling average
- **Top Country** - Most blocked country
- **Peak Day** - Busiest blocking day
- **Weekly Trend** - Week-over-week percentage change

## 🗄️ Data Storage

- **30 days** of detailed daily data
- **12 months** of archived monthly summaries
- **200** recent activity logs
- **Total: ~52 KB** (extremely lightweight)

Monthly archives run automatically on the 1st of each month at 2:00 AM.

## 🔒 What Gets Blocked

✅ Visitors from blocked countries
❌ WordPress admin/dashboard
❌ AJAX requests
❌ REST API calls
❌ VIP support staff

## 📁 File Structure
```
geo-blocker-config.php
geo-blocker-plugin/
├── geo-blocker-plugin.php    # Main plugin
└── templates/
    └── blocked-page.php        # Block page template
```

## 🐛 Troubleshooting

**Not blocking visitors?**
- Verify you're on WP VIP hosting
- Confirm [VIP Geo Uniques](https://github.com/Automattic/vip-go-geo-uniques) is installed and active
- Test from a blocked country (use VPN)

**No stats showing?**
- No visitors from blocked countries yet
- Check if caching is interfering

## 🚀 Upcoming Features

- Component-based architecture
- Dashboard widget visibility toggles
- Custom widget ordering
- CSV export functionality
- Settings page for managing blocked countries via UI
- IP whitelist functionality

## 📝 Version

**1.2.0** - Production ready with automatic archiving

## 👤 Author

Justin Merrell

## 📄 License

GPL v2 or later
