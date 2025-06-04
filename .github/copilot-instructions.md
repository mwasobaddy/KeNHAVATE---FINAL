# KeNHAVATE Innovation Portal - GitHub Copilot Best Practices Guide

This document provides comprehensive guidelines for leveraging GitHub Copilot effectively when developing the KeNHAVATE Innovation Portal, ensuring consistent, secure, and maintainable code that aligns with project specifications.

## Changelog
All notable changes to this project will be documented in the `#changelog.md` file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),  
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of Contents

1. [Project Context and Guidelines](#project-context-and-guidelines)
2. [Authentication and Security Implementation](#authentication-and-security-implementation)
3. [Role-Based Access Control (RBAC)](#role-based-access-control-rbac)
4. [Database and Migration Patterns](#database-and-migration-patterns)
5. [Workflow Implementation Guidelines](#workflow-implementation-guidelines)
6. [UI/UX and Frontend Guidelines](#uiux-and-frontend-guidelines)
7. [Notification and Communication Systems](#notification-and-communication-systems)
8. [Testing and Quality Assurance](#testing-and-quality-assurance)
9. [Audit Trail and Security](#audit-trail-and-security)
10. [Performance and Optimization](#performance-and-optimization)

---

## Project Context and Guidelines

### Core System Overview
When prompting Copilot, always provide this context:

```php
// KeNHAVATE Innovation Portal - Laravel 12 Application
// This is an innovation management system for Kenya National Highways Authority
// Features: Idea submissions, multi-stage reviews, challenge competitions, collaboration
// Architecture: Laravel 12 + Spatie Permissions + Flux UI + Flowbite + GSAP
// Database: MySQL with comprehensive audit trail
// Users: 8 distinct roles with complex permission matrix
```

### Required Package Context
Always inform Copilot about these dependencies:

```php
// Required packages for this project:
// - spatie/laravel-permission (roles & permissions)
// - artesaos/seotools (SEO optimization)  
// - Laravel 12 (latest features)
// - Flux UI components (primary UI)
// - Flowbite (fallback for premium components)
// - GSAP (animations)
```

---

## Authentication and Security Implementation

### OTP Authentication System
When generating OTP-related code, use these patterns:

```php
// OTP Management - Always include these requirements:
// - 15-minute validity period
// - Single-use only
// - 60-second resend cooldown
// - Reuse unexpired OTPs instead of generating new ones
// - Email validation before OTP generation

// Example prompt for Copilot:
// Create an OTP service that generates 6-digit codes valid for 15 minutes,
// prevents duplicate generation for same email, and includes resend logic
class OTPService
{
    public function generateOTP(string $email): array
    {
        // Check for existing unexpired OTP
        $existingOTP = OTP::where('email', $email)
            ->where('expires_at', '>', now())
            ->where('used_at', null)
            ->first();
            
        if ($existingOTP) {
            // Resend existing OTP instead of creating new one
            return [
                'otp' => $existingOTP->otp_code,
                'expires_at' => $existingOTP->expires_at,
                'action' => 'resent'
            ];
        }
        
        // Generate new OTP logic here...
    }
}
```

### Device Tracking Implementation
When implementing device security features:

```php
// Device Fingerprinting - Include these security measures:
// - Browser fingerprinting for device identification
// - Email notifications for new device logins
// - Device trust management
// - IP address and user agent logging

// Example prompt:
// Create a device tracking middleware that fingerprints devices,
// sends email alerts for new devices, and manages trusted device status
```

### Staff Profile Integration
For KeNHA staff-specific features:

```php
// Staff Profile Management - @kenha.co.ke domain handling:
// - Dual table storage (users + staff)
// - Additional required fields for staff registration
// - Personal email validation (must differ from institutional email)
// - Staff number uniqueness constraints

// Example prompt:
// Create a staff registration process that handles @kenha.co.ke emails
// with additional staff-specific fields and validation rules
```

---

## Role-Based Access Control (RBAC)

### Role Definition Context
Always provide this role hierarchy to Copilot:

```php
// KeNHAVATE Role Hierarchy (8 distinct roles):
// 1. Developer - System administration, requires password setup
// 2. Administrator - User management, full idea oversight
// 3. Board Member - Final approval authority, strategic decisions
// 4. Manager - First-stage reviews, challenge creation
// 5. Subject Matter Expert (SME) - Technical evaluation, collaboration guidance
// 6. Challenge Reviewer - Challenge-specific reviews only
// 7. Idea Reviewer - Both stages of idea review process
// 8. User - Base role, idea submission and collaboration

// Each role has specific dashboard requirements and permission sets
// No user can review their own submissions (conflict of interest rule)
```

### Permission Implementation Patterns
When generating permission-related code:

```php
// Permission Middleware Usage - Always include in route definitions:
// Use Spatie permission middleware aliases
Route::middleware(['auth', 'role:manager|admin'])->group(function () {
    // Manager and admin only routes
});

// In Livewire Volt components - Authorization checks:
public function deleteIdea(Idea $idea)
{
    // Always check permissions before actions
    $this->authorize('delete', $idea);
    
    // Additional business logic checks
    if ($idea->current_stage !== 'draft') {
        throw new \Exception('Ideas can only be deleted in draft stage');
    }
}
```

### Dashboard Authorization
For role-specific dashboard generation:

```php
// Dashboard Content Authorization - Each role has specific metrics:
// Developer: System metrics, performance graphs, error tracking
// Board Member: Review performance vs total submissions
// Manager: Idea statistics, challenge creation metrics  
// SME: Review workload, collaboration tracking
// User: Personal submissions, collaboration history

// Example prompt for dashboard components:
// Create a dashboard component for [ROLE] that shows [SPECIFIC_METRICS]
// with appropriate authorization checks and data filtering
```

---

## Database and Migration Patterns

### Migration Strategy
When generating database migrations:

```php
// Database Migration Pattern - Always follow this structure:
// 1. Create migration with foreign key constraints
// 2. Add appropriate indexes for performance
// 3. Include audit trail fields (created_at, updated_at)
// 4. Add soft deletes where appropriate

// Example prompt for migrations:
// Create a migration for [TABLE_NAME] with foreign keys to users table,
// appropriate indexes, and audit trail fields
Schema::create('ideas', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description');
    $table->foreignId('author_id')->constrained('users');
    $table->enum('current_stage', ['draft', 'submitted', 'manager_review', 'sme_review', 'collaboration', 'board_review', 'implementation', 'completed', 'archived'])->default('draft');
    $table->boolean('collaboration_enabled')->default(false);
    $table->timestamps();
    
    // Add indexes for performance
    $table->index(['author_id', 'current_stage']);
    $table->index(['created_at']);
});
```

### Model Relationships
For Eloquent model generation:

```php
// Model Relationship Patterns - Include these standard relationships:
// - belongsTo for foreign keys
// - hasMany for reverse relationships  
// - morphMany for audit trails
// - Spatie permission traits where applicable

// Example prompt:
// Create an Idea model with relationships to User (author), Reviews, 
// Collaborations, and include Spatie permission integration
class Idea extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = ['title', 'description', 'category_id', 'author_id'];
    
    protected $casts = [
        'collaboration_enabled' => 'boolean',
        'submitted_at' => 'datetime',
    ];
    
    // Relationships
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
    
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
```

---

## Workflow Implementation Guidelines

### Multi-Stage Review Process
When implementing the review workflow:

```php
// Review Workflow States - Always maintain this exact sequence:
// draft → submitted → manager_review → sme_review → collaboration (optional) → board_review → implementation → completed/archived

// Business rules to enforce:
// - Ideas are read-only during review stages
// - Only specific roles can transition between stages
// - All transitions must be audited
// - Collaboration can be enabled at SME stage

// Example prompt for workflow logic:
// Create a workflow service that manages idea stage transitions
// with proper authorization, audit logging, and notification triggers
class IdeaWorkflowService
{
    public function transitionStage(Idea $idea, string $newStage, User $user, ?string $comments = null)
    {
        // Validate transition permissions
        $this->validateTransition($idea->current_stage, $newStage, $user);
        
        // Create audit entry
        $this->createAuditLog($idea, $idea->current_stage, $newStage, $user);
        
        // Update idea stage
        $idea->update(['current_stage' => $newStage]);
        
        // Send notifications
        $this->sendNotifications($idea, $newStage);
        
        // Create review record if applicable
        if (in_array($newStage, ['manager_review', 'sme_review', 'board_review'])) {
            $this->createReviewRecord($idea, $user, $comments);
        }
    }
}
```

### Challenge System Implementation
For challenge-related features:

```php
// Challenge System Requirements:
// - Managers create challenges with deadlines
// - Users can participate in multiple challenges simultaneously
// - Challenge-specific review process (Manager → SME → Board/Judge)
// - Winner selection and ranking system
// - Participation limits and tracking

// Example prompt:
// Create a challenge participation system that allows users to submit
// solutions to multiple challenges with proper validation and tracking
```

---

## UI/UX and Frontend Guidelines

### Responsive Design with Flux/Flowbite
When generating UI components:

```blade
{{-- UI Component Guidelines - Always follow these patterns: --}}
{{-- 1. Mobile-first responsive design --}}
{{-- 2. Use Flux components as primary choice --}}
{{-- 3. Fallback to Flowbite for premium/complex components --}}
{{-- 4. Include skeleton loaders for loading states --}}
{{-- 5. GSAP animations for smooth transitions --}}

{{-- Example prompt for UI generation: --}}
{{-- Create a responsive data table using Flowbite with sortable headers, --}}
{{-- search functionality, and skeleton loaders for the ideas management page --}}

<div class="relative overflow-x-auto shadow-md sm:rounded-lg">
    {{-- Search bar --}}
    <div class="pb-4 bg-white">
        <x-flux:input 
            wire:model.live="search" 
            placeholder="Search ideas..." 
            class="w-full"
        />
    </div>
    
    {{-- Loading skeleton --}}
    <div wire:loading class="animate-pulse">
        {{-- Skeleton rows --}}
    </div>
    
    {{-- Actual table content --}}
    <table wire:loading.remove class="w-full text-sm text-left text-gray-500">
        {{-- Table implementation --}}
    </table>
</div>
```

### Color Scheme Implementation
Always use the defined color palette:

```css
/* KeNHAVATE Color Palette - Use these exact colors: */
/* Primary Background: #F8EBD5 (Very Light Beige/Cream) */
/* Primary Text: #231F20 (Very Dark Gray/Off-Black) */
/* Accent/CTA: #FFF200 (Bright Yellow) - use sparingly */
/* Secondary Text/Borders: #9B9EA4 (Medium Gray) */

/* Example Tailwind classes to use: */
/* bg-[#F8EBD5] text-[#231F20] border-[#9B9EA4] accent-[#FFF200] */
```

### Dashboard Layout Patterns
For role-specific dashboard generation:

```blade
{{-- Dashboard Component Pattern - Include these elements: --}}
{{-- 1. Role-appropriate metrics cards --}}
{{-- 2. Charts/graphs using Flowbite --}}
{{-- 3. Quick action buttons --}}
{{-- 4. Recent activity feed --}}
{{-- 5. Notification alerts --}}

{{-- Example prompt: --}}
{{-- Create a Manager dashboard with idea submission statistics, --}}
{{-- challenge creation metrics, and review performance charts --}}
```

---

## Notification and Communication Systems

### Comprehensive Notification System
When implementing notifications:

```php
// Notification Types - Support all these categories:
// 1. status_change - Idea/challenge progression updates  
// 2. review_assigned - New review tasks
// 3. collaboration_request - Invitation to contribute
// 4. deadline_reminder - Upcoming submission/review deadlines
// 5. device_login - Security alerts for new device access
// 6. points_awarded - Gamification achievements

// Example prompt for notification service:
// Create a notification service that handles multiple delivery channels
// (in-app, email) with user preferences and role-based rules
class NotificationService
{
    public function sendNotification(User $user, string $type, array $data)
    {
        // Create database notification
        $notification = $user->notifications()->create([
            'type' => $type,
            'title' => $data['title'],
            'message' => $data['message'],
            'related_id' => $data['related_id'] ?? null,
            'related_type' => $data['related_type'] ?? null,
        ]);
        
        // Send email if user preferences allow
        if ($this->shouldSendEmail($user, $type)) {
            Mail::to($user)->send(new NotificationMail($notification));
        }
        
        // Broadcast real-time notification
        broadcast(new NotificationBroadcast($user, $notification));
    }
}
```

### Messaging System Implementation
For direct messaging features:

```php
// Messaging System Requirements:
// - Direct user-to-manager communication
// - Thread-based conversations on ideas/challenges  
// - Message read status tracking
// - File attachment support
// - Integration with notification system

// Example prompt:
// Create a messaging system that supports threaded conversations
// related to specific ideas or challenges with read receipts
```

---

## Testing and Quality Assurance

### Volt Component Testing
When generating tests for Livewire Volt components:

```php
// Volt Component Test Pattern - Always include these test cases:
// 1. Component renders correctly
// 2. Authentication/authorization checks
// 3. User interactions and state changes
// 4. Validation rules enforcement
// 5. Database operations verification

// Example prompt for test generation:
// Create a comprehensive test suite for the IdeaSubmission Volt component
// including authentication, validation, and workflow state tests
class IdeaSubmissionTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_only_authenticated_users_can_submit_ideas()
    {
        Livewire::test(IdeaSubmission::class)
            ->assertRedirect(route('login'));
    }
    
    public function test_idea_validation_rules_are_enforced()
    {
        $user = User::factory()->create();
        
        Livewire::actingAs($user)
            ->test(IdeaSubmission::class)
            ->set('title', '')
            ->call('save')
            ->assertHasErrors(['title']);
    }
}
```

### Database Testing Patterns
For database-related tests:

```php
// Database Test Guidelines:
// - Use factories for test data generation
// - Test foreign key constraints
// - Verify cascade operations
// - Test unique constraints
// - Validate audit trail creation

// Example prompt:
// Create database tests for the idea submission workflow
// including constraint validation and audit trail verification
```

---

## Audit Trail and Security

### Comprehensive Audit Logging
When implementing audit trails:

```php
// Audit Trail Requirements - Log these action types:
// account_creation, login, idea_submission, challenge_creation,
// challenge_participation, collaboration_invitation, collaboration_request,
// account_banning, account_reporting, review_submission, status_change

// Always capture: user_id, action, entity_type, entity_id, 
// old_values, new_values, ip_address, user_agent, timestamp

// Example prompt for audit service:
// Create an audit logging service that captures all user actions
// with before/after state tracking and security context
class AuditService
{
    public function log(string $action, string $entityType, int $entityId, ?array $oldValues = null, ?array $newValues = null)
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
```

### Security Validation Patterns
For input validation and security:

```php
// Security Validation - Always implement these measures:
// 1. Comprehensive input validation using Laravel rules
// 2. CSRF protection on all forms
// 3. SQL injection prevention through Eloquent
// 4. File upload validation and security
// 5. XSS prevention in display logic

// Example prompt for secure form generation:
// Create a secure idea submission form with comprehensive validation,
// CSRF protection, and XSS prevention measures
```

---

## Performance and Optimization

### Database Query Optimization
When generating database queries:

```php
// Query Optimization Guidelines:
// 1. Use eager loading to prevent N+1 queries
// 2. Add appropriate database indexes
// 3. Implement query scopes for common filters
// 4. Use pagination for large datasets
// 5. Cache frequently accessed data

// Example prompt for optimized queries:
// Create an optimized query to fetch ideas with their authors, reviews,
// and collaboration count, including pagination and filtering options
public function getIdeasWithDetails($filters = [])
{
    return Idea::with(['author', 'reviews.reviewer', 'collaborations'])
        ->withCount('collaborations')
        ->when($filters['stage'] ?? null, function ($query, $stage) {
            $query->where('current_stage', $stage);
        })
        ->when($filters['search'] ?? null, function ($query, $search) {
            $query->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        })
        ->orderBy('created_at', 'desc')
        ->paginate(20);
}
```

### Caching Strategy Implementation
For performance optimization:

```php
// Caching Guidelines:
// 1. Cache user permissions and roles
// 2. Cache dashboard statistics  
// 3. Cache frequently accessed lookup data
// 4. Implement cache invalidation strategies
// 5. Use Redis for session management

// Example prompt for cache implementation:
// Create a caching service for dashboard statistics with
// appropriate invalidation when underlying data changes
```

---

## Common Prompting Patterns

### Context-Rich Prompts
Always provide comprehensive context:

```php
// Example of good prompting for Copilot:
// "Create a Livewire Volt component for the KeNHAVATE Innovation Portal
// that handles idea submission with the following requirements:
// - Uses class-based Volt syntax
// - Implements Spatie permission checks for 'user' role
// - Includes comprehensive validation rules
// - Integrates with the audit logging system
// - Uses Flux UI components with mobile-first design
// - Sends notifications on successful submission
// - Follows the defined color palette (#F8EBD5, #231F20, etc.)
// - Creates appropriate test coverage"
```

### Feature-Specific Prompts
For complex features:

```php
// Multi-stage Review System Prompt:
// "Implement the KeNHAVATE idea review workflow with these stages:
// draft → submitted → manager_review → sme_review → board_review
// Include proper role-based authorization, audit trail logging,
// notification triggers, and state transition validation"

// Challenge System Prompt:
// "Create the challenge competition system allowing managers to create
// challenges, users to submit multiple solutions, and implement the
// review process with winner selection and ranking"
```

---

## Error Handling and Edge Cases

### Comprehensive Error Handling
When generating error-prone code:

```php
// Error Handling Patterns:
// 1. Use Laravel's validation for user input
// 2. Implement custom exceptions for business logic
// 3. Provide user-friendly error messages
// 4. Log errors with appropriate context
// 5. Handle race conditions in workflow transitions

// Example prompt for error handling:
// Create error handling for the idea submission process that covers
// validation errors, permission failures, and concurrent modification issues
```

### Business Logic Validation
For complex business rule implementation:

```php
// Business Rule Validation - Always enforce these rules:
// 1. Users cannot review their own submissions
// 2. Ideas are read-only during review stages  
// 3. Role-specific action permissions
// 4. Deadline enforcement for challenges
// 5. Collaboration invitation limits

// Example prompt:
// Implement business rule validation that prevents users from
// reviewing their own ideas/challenges with appropriate error handling
```

---

## SEO and Performance Considerations

### SEO Implementation with SEOTools
For page SEO optimization:

```php
// SEO Requirements - Implement for all pages:
// - Dynamic page titles based on content
// - Meta descriptions for idea and challenge pages
// - Canonical URLs for proper indexing
// - Open Graph tags for social sharing
// - Schema markup for structured data

// Example prompt for SEO integration:
// Create SEO optimization for the idea detail page using SEOTools
// with dynamic title, description, and proper meta tags
public function mount(Idea $idea)
{
    SEOTools::setTitle($idea->title . ' - KeNHAVATE Innovation Portal');
    SEOTools::setDescription(Str::limit($idea->description, 160));
    SEOTools::setCanonical(route('ideas.show', $idea));
    SEOTools::addImages([asset('images/kenha-logo.png')]);
    
    // Schema markup for idea
    SEOTools::jsonLd()->setType('Article');
    SEOTools::jsonLd()->setTitle($idea->title);
    SEOTools::jsonLd()->setDescription($idea->description);
}
```

---

## Final Implementation Checklist

When Copilot generates code, ensure it includes:

### ✅ Security Checklist
- [ ] Input validation and sanitization
- [ ] Authentication and authorization checks  
- [ ] CSRF protection on forms
- [ ] SQL injection prevention
- [ ] XSS prevention in output

### ✅ Functionality Checklist  
- [ ] Role-based access control
- [ ] Audit trail logging
- [ ] Notification triggers
- [ ] Error handling and validation
- [ ] Mobile-responsive design

### ✅ Performance Checklist
- [ ] Database query optimization
- [ ] Appropriate caching strategy
- [ ] Skeleton loaders for loading states
- [ ] Pagination for large datasets
- [ ] Image optimization and lazy loading

### ✅ Testing Checklist
- [ ] Unit tests for business logic
- [ ] Feature tests for user workflows
- [ ] Authentication and authorization tests
- [ ] Database constraint validation tests
- [ ] UI component tests

### ✅ Code Quality Checklist
- [ ] Following Laravel 12 conventions
- [ ] Proper use of Livewire Volt syntax
- [ ] Consistent code formatting
- [ ] Comprehensive documentation
- [ ] Adherence to project color scheme

---

This comprehensive guide ensures that GitHub Copilot generates code that aligns perfectly with the KeNHAVATE Innovation Portal requirements while maintaining high standards of security, performance, and maintainability.