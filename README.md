# EFU SJT Assessment

A WordPress plugin for managing HOD (Head of Department) Leadership Situational Judgment Assessments for EFU Life.

## Description

EFU SJT Assessment is a comprehensive WordPress plugin designed to administer and score Situational Judgment Tests (SJTs) for leadership assessment. It provides a complete assessment experience including test administration, draft saving, automatic scoring, and Google Sheets integration for result tracking.

### Key Features

- **34-Item Assessment**: Comprehensive SJT with 34 assessment items
- **17 Competencies**: Measures 17 key leadership competencies across 5 pillars
- **Multi-Level Scoring**: Assesses candidates on 4 performance levels (Developing, Proficient, Advanced, Role Model)
- **Draft Saving**: Candidates can save their progress and continue later with a unique token
- **REST API**: Full REST API for submitting assessments and managing drafts
- **Google Sheets Integration**: Automatically export submissions to Google Sheets
- **Admin Dashboard**: Manage questions, settings, submissions, and view analytics
- **Frontend Shortcode**: Simple shortcode to embed the assessment on your website
- **Responsive Design**: Works seamlessly on desktop and mobile devices

## Installation

1. Download the plugin files
2. Upload the `efu-sjt-assessment` folder to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure settings in the admin dashboard

## System Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.2+

## Usage

### Frontend - Display Assessment

Use the shortcode to display the assessment on any page or post:

```php
[efu_sjt_assessment]
```

Or in code:

```php
echo do_shortcode( '[efu_sjt_assessment]' );
```

### Admin - Manage Settings

Navigate to **EFU SJT Assessment** in the WordPress admin menu to:

- **Questions**: View and edit assessment questions
- **Settings**: Configure plugin behavior and scoring options
- **Sheets**: Configure Google Sheets integration
- **Submissions**: View candidate submissions and responses

### REST API Endpoints

#### Submit Assessment

**Endpoint**: `POST /wp-json/efu-sjt/v1/submit`

Submit a completed assessment:

```json
{
  "email": "user@example.com",
  "name": "John Doe",
  "responses": [
    {
      "item_id": "item_1",
      "option_id": "option_a"
    }
  ]
}
```

#### Save Draft

**Endpoint**: `POST /wp-json/efu-sjt/v1/draft`

Save assessment progress:

```json
{
  "token": "unique_draft_token",
  "step": 1,
  "responses": [...]
}
```

#### Load Draft

**Endpoint**: `GET /wp-json/efu-sjt/v1/draft/{token}`

Retrieve saved draft responses with the provided token.

#### Delete Draft

**Endpoint**: `DELETE /wp-json/efu-sjt/v1/draft/{token}`

Delete a saved draft.

## Scoring System

The assessment uses a weighted scoring formula:

**Formula**: `(4 × L4_pts + 3 × L3_pts + 2 × L2_pts + 1 × L1_pts) / 20`

### Performance Bands

| Score Range | Level | Description |
|-------------|-------|-------------|
| 1.0 - 1.9 | L1 | Developing |
| 2.0 - 2.6 | L2 | Proficient |
| 2.7 - 3.3 | L3 | Advanced |
| 3.4 - 4.0 | L4 | Role Model |

## Assessment Structure

### 5 Pillars of Leadership

1. **Growth & Strategy**
2. **People & Team Leadership**
3. **Operational Excellence**
4. **Values & Culture**
5. **Innovation & Adaptability**

Each pillar contains multiple competencies assessed through situational judgment scenarios.

## Database Structure

The plugin creates a custom table: `{wp_prefix}efu_sjt_submissions`

### Submissions Table Schema

```
- id: Primary identifier
- email: Candidate email
- name: Candidate name
- responses: JSON-encoded responses
- scores: JSON-encoded competency scores
- status: Submission status (submitted, draft)
- submitted_at: Submission timestamp
- created_at: Creation timestamp
```

## Plugin Files Overview

```
efu-sjt-assessment/
├── efu-sjt-assessment.php       # Main plugin file
├── uninstall.php                # Uninstallation cleanup
├── README.md                    # This file
├── admin/                       # Admin functionality
│   ├── class-admin.php          # Admin page initialization
│   ├── page-questions.php       # Questions management page
│   ├── page-settings.php        # Settings page
│   ├── page-sheets.php          # Google Sheets configuration
│   └── page-submissions.php     # Submissions view page
├── frontend/                    # Frontend functionality
│   ├── class-shortcode.php      # Shortcode implementation
│   └── page-thankyou.php        # Thank you page template
├── includes/                    # Core functionality
│   ├── class-activator.php      # Plugin activation
│   ├── class-deactivator.php    # Plugin deactivation
│   ├── class-submission.php     # Submission handling
│   ├── class-scorer.php         # Assessment scoring logic
│   ├── class-rest-api.php       # REST API endpoints
│   └── class-google-sheets.php  # Google Sheets integration
├── assets/                      # Static assets
│   ├── css/
│   │   ├── admin.css            # Admin styles
│   │   └── frontend.css         # Frontend styles
│   └── js/
│       ├── admin-charts.js      # Admin chart functionality
│       ├── admin-questions.js   # Admin question management
│       └── frontend-quiz.js     # Frontend assessment logic
└── data/
    └── assessment.json          # Assessment questions & scoring config
```

## Configuration

### Google Sheets Integration

1. Go to **EFU SJT Assessment → Sheets** in admin
2. Enter your Google Sheets API credentials
3. Specify the target Google Sheet for submissions
4. Submissions will automatically export upon completion

### Custom Settings

Settings can be configured through the admin panel:

- Assessment title and description
- Number of items per page
- Required fields for submission
- Email notifications
- Scoring options

## Development

### Hooks and Filters

#### Actions

- `efu_sjt_assessment_submitted`: Fired when assessment is submitted
- `efu_sjt_assessment_draft_saved`: Fired when draft is saved
- `efu_sjt_assessment_scored`: Fired when assessment is scored

#### Filters

- `efu_sjt_assessment_questions`: Filter assessment questions
- `efu_sjt_assessment_scores`: Filter calculated scores
- `efu_sjt_assessment_response`: Filter submission response

### Example Usage

```php
// Hook into assessment submission
add_action( 'efu_sjt_assessment_submitted', function( $submission_id, $email, $responses ) {
    // Custom logic after submission
    error_log( 'Assessment submitted by: ' . $email );
}, 10, 3 );

// Filter assessment scores
add_filter( 'efu_sjt_assessment_scores', function( $scores, $responses ) {
    // Modify scores if needed
    return $scores;
}, 10, 2 );
```

## Troubleshooting

### Assessment Not Displaying

- Ensure the shortcode `[efu_sjt_assessment]` is correctly placed
- Check that JavaScript files are loading (check browser console)
- Verify CSS is not conflicting with theme styles

### Submissions Not Saving

- Check WordPress REST API is enabled
- Verify database table was created on activation
- Check server error logs for database errors

### Google Sheets Export Issues

- Verify API credentials are correct
- Ensure service account has access to the target Sheet
- Check that Sheet ID is correctly configured

## Changelog

### Version 1.1.0
- Initial release with full SJT assessment functionality
- REST API implementation
- Google Sheets integration
- Admin dashboard

## Support & Contribution

For issues, questions, or contributions, please contact:

**Author**: Sumair Ahmed | Trout Digital  
**Website**: https://trout.digital/

## License

This plugin is licensed under the GPL-2.0+ license. See the main plugin file for details.

## Credits

Developed for EFU Life Leadership Development Program.
