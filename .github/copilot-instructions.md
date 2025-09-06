# AI Coding Instructions for Language Center LMS

This is a Laravel-based Learning Management System for language centers with multi-branch support and role-based access control.

## Project Architecture

### Core Technology Stack
- **Backend**: Laravel 12.24.0 with PHP 8.2+
- **Frontend**: Vue 3 + Inertia.js (SPA architecture)
- **UI Framework**: PrimeVue 4 with Aura theme
- **Styling**: TailwindCSS with dark mode support
- **Charts**: Chart.js 4.5.0
- **Authentication**: Laravel Breeze with Spatie Permission for roles
- **Database**: MySQL with Eloquent ORM
- **Asset Building**: Vite
- **Queue System**: Laravel Horizon with Redis

### Database Schema Conventions
- **Tables naming**: Plural (e.g., `classrooms`, `class_sessions`, `teaching_assignments`)
- **Primary keys**: `id` (auto-increment)
- **Foreign keys**: `{model}_id` (e.g., `class_id`, `teacher_id`, `student_id`)
- **Timestamps**: All models have `created_at` and `updated_at`
- **Soft deletes**: Not used; use `active` boolean or status enums instead
- **Status fields**: Use enums (e.g., `['open','closed']`, `['planned','canceled','moved']`)

### Key Database Tables
```
users (id, name, email, phone, password, active)
branches (id, name, address, active)
manager_branch (user_id, branch_id) // Many-to-many pivot
courses (id, code, name, audience, language, active)
classrooms (id, code, name, term_code, course_id, branch_id, start_date, sessions_total, tuition_fee, status)
class_schedules (id, class_id, day_of_week, start_time, end_time)
class_sessions (id, class_id, session_no, date, start_time, end_time, room_id, status, note)
enrollments (id, student_id, class_id, enrolled_at, status)
teaching_assignments (id, teacher_id, class_id, effective_from, effective_to)
teacher_timesheets (id, teacher_id, class_session_id, amount, status)
attendances (id, student_id, class_session_id, status, note)
transfers (id, student_id, from_class_id, to_class_id, status, reason)
invoices (id, student_id, total_amount, paid_amount, status)
payments (id, invoice_id, amount, payment_date, method)
```

## Role-Based Access Control

### Roles & Permissions
- **admin**: Full system access, can manage all branches
- **manager**: Branch-scoped access via `manager_branch` pivot table
- **teacher**: Access to own classes and attendance management
- **student**: Limited access to own data (future scope)

### Route Structure
```
/admin/*     - Admin-only routes (role:admin middleware)
/manager/*   - Manager routes (role:manager middleware)  
/teacher/*   - Teacher routes (role:teacher middleware)
/manager/*   - Shared admin/manager routes (role:admin|manager middleware)
```

### Branch Scoping Pattern
For managers, always scope queries by their assigned branches:
```php
$user = auth()->user();
$branchIds = $user->managerBranches()->pluck('branches.id')->all();
$query->whereIn('branch_id', $branchIds);
```

## Frontend Architecture

### Inertia.js Patterns
- All pages are Vue components in `resources/js/Pages/{Role}/`
- Use Inertia::render() in controllers, pass data as props
- Navigation via `router.visit()` or `<Link>` component
- Flash messages handled automatically in AppLayout

### Component Organization
```
resources/js/
├── app.js                 // Main entry point with PrimeVue setup
├── Layouts/
│   └── AppLayout.vue     // Main layout with sidebar, role-based menu
├── Pages/
│   ├── Admin/           // Admin-specific pages
│   ├── Manager/         // Manager-specific pages  
│   └── Teacher/         // Teacher-specific pages
└── composables/
    └── usePageToast.js  // Toast notifications utility
```

### PrimeVue Integration
- All PrimeVue components are available globally
- Aura theme with dark mode support (`.dark` class)
- Toast service configured for notifications
- Common components: Card, Chart, Button, DataTable, Dialog

### Chart.js Setup
Chart.js is fully configured with all chart types:
```javascript
import {
    Chart as ChartJS,
    CategoryScale, LinearScale, PointElement, LineElement, 
    BarElement, ArcElement, Title, Tooltip, Legend, Colors
} from 'chart.js'
```

## Development Patterns

### Controller Conventions
1. **Single Action Controllers**: Use `__invoke()` for simple actions
2. **Resource Controllers**: Use standard CRUD methods (index, create, store, show, edit, update, destroy)
3. **Nested Resources**: Use route model binding with scoped parameters
4. **API Responses**: Return Inertia responses for pages, JSON for AJAX

### Model Relationships
Define relationships clearly with proper return types:
```php
public function enrollments(): HasMany
{
    return $this->hasMany(Enrollment::class, 'class_id');
}

public function currentTeachingAssignment(): HasOne
{
    return $this->hasOne(TeachingAssignment::class, 'class_id')
        ->whereNull('effective_to');
}
```

### Query Scoping
Use Eloquent scopes for reusable query logic:
```php
public function scopeWithCurrentTeacher($query)
{
    return $query->addSelect([
        'current_teacher_id' => TeachingAssignment::select('teacher_id')
            ->whereColumn('class_id', 'classrooms.id')
            ->whereNull('effective_to')
            ->limit(1)
    ])->with(['currentTeachingAssignment.teacher']);
}
```

### Route Naming Conventions
- Admin routes: `admin.{resource}.{action}`
- Manager routes: `manager.{resource}.{action}`
- Teacher routes: `teacher.{resource}.{action}`
- Nested routes: `admin.classrooms.schedules.index`

## Frontend Development Guidelines

### Vue 3 Composition API
Always use Composition API with `<script setup>`:
```vue
<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { usePageToast } from '@/composables/usePageToast'

const page = usePage()
const { showSuccess, showError } = usePageToast()

// Props
const props = defineProps({
    kpi: Object,
    charts: Object
})
</script>
```

### Styling Conventions
- Use TailwindCSS utility classes
- Support dark mode with `dark:` variants
- Color palette: Primary green `#10b981`, backgrounds `#f6f8fa` (light) / `#181c23` (dark)
- Consistent spacing: `p-3 md:p-5` for main content

### State Management
- No Vuex/Pinia - use Inertia props and local component state
- Flash messages via Laravel session flash
- Form handling with Inertia forms

## Dashboard Development

### KPI Card Pattern
All dashboards follow this KPI structure:
```javascript
kpi: {
    students: { total: 150, growth: 12.5 },
    classes: { total: 25, growth: -2.1 },
    teachers: { total: 8, growth: 0 }
}
```

### Chart Data Format
Standardized chart data structures:
```javascript
charts: {
    enrollment_trend: [{ month: 'Jan 2024', value: 45 }],
    attendance_by_class: [{ name: 'Class A', rate: 85.2 }],
    students_by_course: [{ name: 'English Basic', value: 30 }]
}
```

### Recent Activities Pattern
Consistent structure for activity feeds:
```javascript
recent: {
    transfers: [...],        // Latest transfers with student/class data
    attendance_today: [...], // Today's attendance summary
    pending_timesheets: [...] // Pending approval items
}
```

## Error Handling & Validation

### Form Validation
- Use Laravel Form Requests for validation
- Display errors via Inertia's error prop
- Toast notifications for success/error feedback

### Exception Handling
- Use try-catch blocks for database operations
- Log errors appropriately with context
- Return user-friendly error messages

### Safety Checks
Implement safety validations for critical operations:
```php
// Check invoice safety before transfer operations
public function checkRevertSafety(Request $request)
{
    $transfer = Transfer::findOrFail($request->transfer_id);
    $safety = app(InvoiceSafetyService::class);
    
    return response()->json([
        'safe' => $safety->canRevertTransfer($transfer),
        'issues' => $safety->getIssues()
    ]);
}
```

## Testing Guidelines

### Database Testing
- Use factories for test data generation
- Seeders for demo data and development
- Feature tests for controller actions
- Unit tests for business logic

### Frontend Testing
- Test component rendering with props
- Verify user interactions work correctly
- Test form submissions and validations

## Security Considerations

### Authentication & Authorization
- Always use middleware for route protection
- Verify user has access to requested resources
- Branch scoping for managers is mandatory

### Data Protection
- Validate all inputs server-side
- Use parameterized queries (Eloquent handles this)
- Sanitize output when displaying user data

## Performance Optimization

### Database Queries
- Use eager loading to prevent N+1 queries
- Index foreign keys and frequently queried columns
- Use database-level aggregations for statistics

### Frontend Performance
- Lazy load heavy components
- Optimize chart rendering for large datasets
- Use computed properties for expensive calculations

## Deployment & Environment

### Laravel Configuration
- Environment-specific configs in `.env`
- Queue workers for background jobs
- Redis for caching and sessions
- Database migrations for schema changes

### Asset Compilation
- `npm run dev` for development with hot reloading
- `npm run build` for production builds
- Vite handles all asset bundling and optimization

## Code Style & Standards

### PHP Standards
- Follow PSR-12 coding standards
- Use Laravel Pint for code formatting
- Descriptive variable and method names
- Proper docblocks for complex methods

### JavaScript/Vue Standards
- Use ESLint for code consistency
- Prefer arrow functions and destructuring
- Use TypeScript-style comments for prop validation
- Consistent indentation (2 spaces)

## Common Patterns & Utilities

### Date Handling
Use Carbon for all date operations:
```php
$today = Carbon::today();
$monthStart = Carbon::now()->startOfMonth();
$dateRange = [$monthStart, $monthEnd];
```

### Toast Notifications
Standard toast patterns:
```javascript
const { showSuccess, showError, showInfo } = usePageToast()
showSuccess('Operation completed', 'Data saved successfully')
showError('Error occurred', 'Please try again')
```

### API Responses
Consistent JSON response format:
```php
return response()->json([
    'success' => true,
    'data' => $data,
    'message' => 'Operation completed'
]);
```

## Development Workflow

### Feature Development
1. Create migration for database changes
2. Update/create models with relationships
3. Create/update controllers with proper validation
4. Add routes with appropriate middleware
5. Create/update Vue components
6. Test functionality thoroughly
7. Add to navigation menu if needed

### Debugging
- Use Laravel Telescope for request debugging
- Browser DevTools for frontend issues
- Check Laravel logs for server errors
- Use `dd()` and `dump()` for quick debugging

## Documentation Standards

### Code Comments
- Document complex business logic
- Explain non-obvious relationships
- Add TODO comments for future improvements
- Include examples for complex methods

### API Documentation
- Document all controller methods
- Include request/response examples
- Note required permissions and middlewares
- Explain validation rules

This comprehensive guide should help any AI assistant understand the codebase structure and follow consistent development patterns when working on this Language Center LMS project.
