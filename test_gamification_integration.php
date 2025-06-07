<?php

/**
 * KeNHAVATE Gamification System Integration Test
 * Tests the complete gamification implementation across all dashboards
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;

class GamificationIntegrationTest
{
    private $results = [];
    
    public function runTests()
    {
        echo "🎮 KeNHAVATE Gamification System Integration Test\n";
        echo "=" . str_repeat("=", 60) . "\n\n";
        
        $this->testServiceIntegration();
        $this->testDashboardIntegration();
        $this->testComponentIntegration();
        $this->testWorkflowIntegration();
        $this->testDatabaseStructure();
        
        $this->displayResults();
    }
    
    private function testServiceIntegration()
    {
        echo "📋 Testing Service Integration...\n";
        
        // Test GamificationService
        $gamificationServicePath = '/Users/app/Desktop/Laravel/KeNHAVATE/app/Services/GamificationService.php';
        if (file_exists($gamificationServicePath)) {
            $content = file_get_contents($gamificationServicePath);
            
            $this->checkMethod($content, 'awardPoints', 'GamificationService::awardPoints');
            $this->checkMethod($content, 'checkAchievements', 'GamificationService::checkAchievements');
            $this->checkMethod($content, 'getLeaderboard', 'GamificationService::getLeaderboard');
            $this->checkMethod($content, 'getRoleBasedLeaderboard', 'GamificationService::getRoleBasedLeaderboard');
            $this->checkMethod($content, 'getDepartmentLeaderboard', 'GamificationService::getDepartmentLeaderboard');
            
            // Check for all point award types
            $pointTypes = [
                'account_creation', 'daily_login', 'idea_submission', 'challenge_participation',
                'first_half_reviewer_idea', 'first_half_reviewer_challenge', 'early_review_bonus',
                'login_streak', 'weekend_warrior', 'challenge_winner', 'innovation_milestone'
            ];
            
            foreach ($pointTypes as $type) {
                if (strpos($content, "'$type'") !== false) {
                    $this->results[] = "✅ Point type '$type' implemented";
                } else {
                    $this->results[] = "❌ Point type '$type' missing";
                }
            }
        } else {
            $this->results[] = "❌ GamificationService.php not found";
        }
        
        // Test AchievementService
        $achievementServicePath = '/Users/app/Desktop/Laravel/KeNHAVATE/app/Services/AchievementService.php';
        if (file_exists($achievementServicePath)) {
            $content = file_get_contents($achievementServicePath);
            $this->checkMethod($content, 'checkAchievements', 'AchievementService::checkAchievements');
            $this->checkMethod($content, 'getUserAchievements', 'AchievementService::getUserAchievements');
            $this->checkMethod($content, 'getAchievementDistribution', 'AchievementService::getAchievementDistribution');
        } else {
            $this->results[] = "❌ AchievementService.php not found";
        }
        
        // Test Other Services
        $services = [
            'DailyLoginService.php' => 'Daily login tracking',
            'ReviewTrackingService.php' => 'Review bonus tracking',
            'ChallengeWorkflowService.php' => 'Challenge workflow management'
        ];
        
        foreach ($services as $file => $description) {
            $path = '/Users/app/Desktop/Laravel/KeNHAVATE/app/Services/' . $file;
            if (file_exists($path)) {
                $this->results[] = "✅ $description service implemented";
            } else {
                $this->results[] = "❌ $description service missing";
            }
        }
        
        echo "\n";
    }
    
    private function testDashboardIntegration()
    {
        echo "🎯 Testing Dashboard Integration...\n";
        
        $dashboards = [
            'user-dashboard.blade.php' => 'User Dashboard',
            'manager-dashboard.blade.php' => 'Manager Dashboard', 
            'admin-dashboard.blade.php' => 'Admin Dashboard',
            'sme-dashboard.blade.php' => 'SME Dashboard',
            'board-member-dashboard.blade.php' => 'Board Member Dashboard',
            'challenge-reviewer-dashboard.blade.php' => 'Challenge Reviewer Dashboard'
        ];
        
        foreach ($dashboards as $file => $name) {
            $path = '/Users/app/Desktop/Laravel/KeNHAVATE/resources/views/livewire/dashboard/' . $file;
            if (file_exists($path)) {
                $content = file_get_contents($path);
                
                // Check for gamification components
                $hasPointsWidget = strpos($content, 'livewire:components.points-widget') !== false;
                $hasLeaderboard = strpos($content, 'livewire:components.leaderboard') !== false;
                $hasNotifications = strpos($content, 'livewire:components.achievement-notifications') !== false;
                $hasAchievementService = strpos($content, 'AchievementService') !== false;
                
                if ($hasPointsWidget && $hasLeaderboard && $hasNotifications) {
                    $this->results[] = "✅ $name fully integrated with gamification";
                } else {
                    $missing = [];
                    if (!$hasPointsWidget) $missing[] = 'points-widget';
                    if (!$hasLeaderboard) $missing[] = 'leaderboard';
                    if (!$hasNotifications) $missing[] = 'achievement-notifications';
                    $this->results[] = "⚠️ $name missing: " . implode(', ', $missing);
                }
            } else {
                $this->results[] = "❌ $name file not found";
            }
        }
        
        echo "\n";
    }
    
    private function testComponentIntegration()
    {
        echo "🧩 Testing Component Integration...\n";
        
        $components = [
            'points-widget.blade.php' => 'Points Widget',
            'leaderboard.blade.php' => 'Leaderboard',
            'achievement-notifications.blade.php' => 'Achievement Notifications',
            'points-history.blade.php' => 'Points History'
        ];
        
        foreach ($components as $file => $name) {
            $path = '/Users/app/Desktop/Laravel/KeNHAVATE/resources/views/livewire/components/' . $file;
            if (file_exists($path)) {
                $content = file_get_contents($path);
                
                // Check for proper Livewire Volt syntax
                $hasVoltSyntax = strpos($content, 'new class extends Component') !== false;
                $hasGamificationService = strpos($content, 'GamificationService') !== false;
                
                if ($hasVoltSyntax) {
                    $this->results[] = "✅ $name component properly structured";
                } else {
                    $this->results[] = "⚠️ $name may have syntax issues";
                }
            } else {
                $this->results[] = "❌ $name component not found";
            }
        }
        
        echo "\n";
    }
    
    private function testWorkflowIntegration()
    {
        echo "🔄 Testing Workflow Integration...\n";
        
        $workflows = [
            'auth/login.blade.php' => 'Login workflow',
            'auth/register.blade.php' => 'Registration workflow',
            'challenges/submit.blade.php' => 'Challenge submission',
            'challenges/review-submission.blade.php' => 'Challenge review'
        ];
        
        foreach ($workflows as $file => $name) {
            $path = '/Users/app/Desktop/Laravel/KeNHAVATE/resources/views/livewire/' . $file;
            if (file_exists($path)) {
                $content = file_get_contents($path);
                
                $hasGamificationIntegration = strpos($content, 'GamificationService') !== false ||
                                            strpos($content, 'DailyLoginService') !== false ||
                                            strpos($content, 'ChallengeWorkflowService') !== false;
                
                if ($hasGamificationIntegration) {
                    $this->results[] = "✅ $name integrated with gamification";
                } else {
                    $this->results[] = "⚠️ $name may need gamification integration";
                }
            } else {
                $this->results[] = "❌ $name file not found";
            }
        }
        
        // Check IdeaWorkflowService
        $ideaWorkflowPath = '/Users/app/Desktop/Laravel/KeNHAVATE/app/Services/IdeaWorkflowService.php';
        if (file_exists($ideaWorkflowPath)) {
            $content = file_get_contents($ideaWorkflowPath);
            if (strpos($content, 'GamificationService') !== false) {
                $this->results[] = "✅ IdeaWorkflowService integrated with gamification";
            } else {
                $this->results[] = "⚠️ IdeaWorkflowService needs gamification integration";
            }
        }
        
        echo "\n";
    }
    
    private function testDatabaseStructure()
    {
        echo "🗄️ Testing Database Structure...\n";
        
        // Check UserPoint model
        $userPointPath = '/Users/app/Desktop/Laravel/KeNHAVATE/app/Models/UserPoint.php';
        if (file_exists($userPointPath)) {
            $content = file_get_contents($userPointPath);
            
            $hasProperFillable = strpos($content, "'action'") !== false;
            $hasScopes = strpos($content, 'scope') !== false;
            
            if ($hasProperFillable && $hasScopes) {
                $this->results[] = "✅ UserPoint model properly configured";
            } else {
                $this->results[] = "⚠️ UserPoint model may need updates";
            }
        } else {
            $this->results[] = "❌ UserPoint model not found";
        }
        
        // Check User model
        $userPath = '/Users/app/Desktop/Laravel/KeNHAVATE/app/Models/User.php';
        if (file_exists($userPath)) {
            $content = file_get_contents($userPath);
            
            $hasPointsMethods = strpos($content, 'totalPoints') !== false &&
                              strpos($content, 'monthlyPoints') !== false &&
                              strpos($content, 'getRankingPosition') !== false;
            
            if ($hasPointsMethods) {
                $this->results[] = "✅ User model has gamification methods";
            } else {
                $this->results[] = "⚠️ User model missing gamification methods";
            }
        }
        
        echo "\n";
    }
    
    private function checkMethod($content, $method, $context)
    {
        if (strpos($content, "function $method") !== false) {
            $this->results[] = "✅ $context method implemented";
        } else {
            $this->results[] = "❌ $context method missing";
        }
    }
    
    private function displayResults()
    {
        echo "📊 TEST RESULTS SUMMARY\n";
        echo "=" . str_repeat("=", 60) . "\n";
        
        $passed = 0;
        $warnings = 0;
        $failed = 0;
        
        foreach ($this->results as $result) {
            echo $result . "\n";
            
            if (strpos($result, '✅') === 0) {
                $passed++;
            } elseif (strpos($result, '⚠️') === 0) {
                $warnings++;
            } elseif (strpos($result, '❌') === 0) {
                $failed++;
            }
        }
        
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "✅ Passed: $passed\n";
        echo "⚠️ Warnings: $warnings\n";
        echo "❌ Failed: $failed\n";
        echo "📈 Total Tests: " . count($this->results) . "\n";
        
        $successRate = round(($passed / count($this->results)) * 100, 1);
        echo "🎯 Success Rate: $successRate%\n";
        
        if ($successRate >= 90) {
            echo "\n🎉 EXCELLENT! Gamification system is well integrated!\n";
        } elseif ($successRate >= 75) {
            echo "\n👍 GOOD! Minor issues to address.\n";
        } elseif ($successRate >= 50) {
            echo "\n⚠️ NEEDS WORK! Several components need attention.\n";
        } else {
            echo "\n❌ CRITICAL! Major integration issues detected.\n";
        }
        
        echo "\n🔧 NEXT STEPS:\n";
        echo "1. Address any failed components\n";
        echo "2. Test real-time point awarding\n";
        echo "3. Validate achievement notifications\n";
        echo "4. Test dashboard performance with gamification data\n";
        echo "5. Verify all workflows award points correctly\n";
    }
}

// Run the test
$test = new GamificationIntegrationTest();
$test->runTests();
