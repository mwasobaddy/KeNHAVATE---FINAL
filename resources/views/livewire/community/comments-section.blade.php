<?php

use Livewire\Volt\Component;
use App\Models\Comment;
use App\Models\CommentVote;
use App\Models\Idea;

new class extends Component {
    public $commentable;
    public $commentableType;
    public $commentableId;
    public $newComment = '';
    public $replyingTo = null;
    public $replyContent = '';
    public $comments = [];
    public $perPage = 10;
    public $showAll = false;

    public function mount($commentable = null, $commentableType = null, $commentableId = null)
    {
        if ($commentable) {
            $this->commentable = $commentable;
            $this->commentableType = get_class($commentable);
            $this->commentableId = $commentable->id;
        } else {
            $this->commentableType = $commentableType;
            $this->commentableId = $commentableId;
        }
        
        $this->loadComments();
    }

    public function loadComments()
    {
        $query = Comment::with(['author', 'replies.author', 'votes'])
            ->where('commentable_type', $this->commentableType)
            ->where('commentable_id', $this->commentableId)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc');

        $this->comments = $this->showAll 
            ? $query->get()
            : $query->take($this->perPage)->get();
    }

    public function addComment()
    {
        $this->validate([
            'newComment' => 'required|string|min:3|max:1000',
        ]);

        $comment = Comment::create([
            'content' => $this->newComment,
            'author_id' => auth()->id(),
            'commentable_type' => $this->commentableType,
            'commentable_id' => $this->commentableId,
        ]);

        // Create audit log
        app('audit')->log('comment_created', 'Comment', $comment->id, null, [
            'content' => $this->newComment,
            'commentable_type' => $this->commentableType,
            'commentable_id' => $this->commentableId,
        ]);

        $this->newComment = '';
        $this->loadComments();
        
        session()->flash('message', 'Comment added successfully!');
    }

    public function addReply()
    {
        $this->validate([
            'replyContent' => 'required|string|min:3|max:1000',
            'replyingTo' => 'required|exists:comments,id',
        ]);

        $parentComment = Comment::findOrFail($this->replyingTo);

        $reply = Comment::create([
            'content' => $this->replyContent,
            'author_id' => auth()->id(),
            'commentable_type' => $this->commentableType,
            'commentable_id' => $this->commentableId,
            'parent_id' => $this->replyingTo,
        ]);

        // Create audit log
        app('audit')->log('comment_reply_created', 'Comment', $reply->id, null, [
            'content' => $this->replyContent,
            'parent_id' => $this->replyingTo,
        ]);

        $this->replyContent = '';
        $this->replyingTo = null;
        $this->loadComments();
        
        session()->flash('message', 'Reply added successfully!');
    }

    public function voteComment($commentId, $voteType)
    {
        $comment = Comment::findOrFail($commentId);
        
        // Check if user already voted
        $existingVote = CommentVote::where('comment_id', $commentId)
            ->where('user_id', auth()->id())
            ->first();

        if ($existingVote) {
            if ($existingVote->type === $voteType) {
                // Remove vote if clicking same vote type
                $existingVote->delete();
                $this->updateVoteCounts($comment);
                return;
            } else {
                // Update vote type
                $existingVote->update(['type' => $voteType]);
            }
        } else {
            // Create new vote
            CommentVote::create([
                'comment_id' => $commentId,
                'user_id' => auth()->id(),
                'type' => $voteType,
            ]);
        }

        $this->updateVoteCounts($comment);
        $this->loadComments();
    }

    private function updateVoteCounts($comment)
    {
        $upvotes = $comment->votes()->where('type', 'upvote')->count();
        $downvotes = $comment->votes()->where('type', 'downvote')->count();
        
        $comment->update([
            'upvotes_count' => $upvotes,
            'downvotes_count' => $downvotes,
        ]);
    }

    public function startReply($commentId)
    {
        $this->replyingTo = $commentId;
        $this->replyContent = '';
    }

    public function cancelReply()
    {
        $this->replyingTo = null;
        $this->replyContent = '';
    }

    public function showAllComments()
    {
        $this->showAll = true;
        $this->loadComments();
    }

    public function deleteComment($commentId)
    {
        $comment = Comment::findOrFail($commentId);
        
        // Check authorization
        if ($comment->author_id !== auth()->id() && !auth()->user()->hasRole(['admin', 'developer'])) {
            abort(403);
        }

        $comment->delete();
        $this->loadComments();
        
        session()->flash('message', 'Comment deleted successfully!');
    }
}; ?>

<div class="space-y-6">
    <!-- Comments Header -->
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-[#231F20]">
            Comments ({{ $comments->count() }})
        </h3>
        
        @if(!$showAll && $comments->count() >= $perPage)
            <button 
                wire:click="showAllComments"
                class="text-sm text-blue-600 hover:text-blue-800 transition-colors"
            >
                Show all comments
            </button>
        @endif
    </div>

    <!-- Add New Comment -->
    @auth
        <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
            <form wire:submit.prevent="addComment" class="space-y-4">
                <div>
                    <label for="newComment" class="sr-only">Add a comment</label>
                    <textarea
                        wire:model="newComment"
                        id="newComment"
                        rows="3"
                        class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none bg-white/80 backdrop-blur-sm"
                        placeholder="Share your thoughts, suggestions, or questions..."
                    ></textarea>
                    @error('newComment') 
                        <span class="text-red-600 text-sm mt-1">{{ $message }}</span> 
                    @enderror
                </div>
                
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">
                        {{ 1000 - strlen($newComment) }} characters remaining
                    </span>
                    <button
                        type="submit"
                        class="px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 font-medium"
                        {{ strlen($newComment) < 3 ? 'disabled' : '' }}
                    >
                        Post Comment
                    </button>
                </div>
            </form>
        </div>
    @endauth

    <!-- Comments List -->
    <div class="space-y-4">
        @forelse($comments as $comment)
            <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
                <!-- Comment Header -->
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full flex items-center justify-center text-white text-sm font-medium">
                            {{ $comment->author->initials() }}
                        </div>
                        <div>
                            <h4 class="font-medium text-[#231F20]">{{ $comment->author->name }}</h4>
                            <p class="text-sm text-gray-500">{{ $comment->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    
                    @if($comment->author_id === auth()->id() || auth()->user()?->hasRole(['admin', 'developer']))
                        <button
                            wire:click="deleteComment({{ $comment->id }})"
                            wire:confirm="Are you sure you want to delete this comment?"
                            class="text-red-600 hover:text-red-800 text-sm"
                        >
                            Delete
                        </button>
                    @endif
                </div>

                <!-- Comment Content -->
                <div class="mb-4">
                    <p class="text-[#231F20] leading-relaxed">{{ $comment->content }}</p>
                </div>

                <!-- Comment Actions -->
                <div class="flex items-center space-x-4">
                    @auth
                        <!-- Voting -->
                        <div class="flex items-center space-x-2">
                            <button
                                wire:click="voteComment({{ $comment->id }}, 'upvote')"
                                class="flex items-center space-x-1 text-sm {{ auth()->user()->hasVotedOnComment($comment) && auth()->user()->getCommentVote($comment)?->type === 'upvote' ? 'text-green-600' : 'text-gray-500 hover:text-green-600' }} transition-colors"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                                <span>{{ $comment->upvotes_count }}</span>
                            </button>
                            
                            <button
                                wire:click="voteComment({{ $comment->id }}, 'downvote')"
                                class="flex items-center space-x-1 text-sm {{ auth()->user()->hasVotedOnComment($comment) && auth()->user()->getCommentVote($comment)?->type === 'downvote' ? 'text-red-600' : 'text-gray-500 hover:text-red-600' }} transition-colors"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                <span>{{ $comment->downvotes_count }}</span>
                            </button>
                        </div>

                        <!-- Reply Button -->
                        <button
                            wire:click="startReply({{ $comment->id }})"
                            class="text-sm text-gray-500 hover:text-blue-600 transition-colors"
                        >
                            Reply
                        </button>
                    @endauth
                </div>

                <!-- Reply Form -->
                @auth
                    @if($replyingTo === $comment->id)
                        <div class="mt-4 p-4 bg-gray-50/80 backdrop-blur-sm rounded-lg">
                            <form wire:submit.prevent="addReply" class="space-y-3">
                                <textarea
                                    wire:model="replyContent"
                                    rows="2"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none bg-white/80"
                                    placeholder="Write your reply..."
                                ></textarea>
                                @error('replyContent') 
                                    <span class="text-red-600 text-sm">{{ $message }}</span> 
                                @enderror
                                
                                <div class="flex justify-end space-x-2">
                                    <button
                                        type="button"
                                        wire:click="cancelReply"
                                        class="px-4 py-2 text-gray-600 hover:text-gray-800 text-sm"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm"
                                    >
                                        Reply
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                @endauth

                <!-- Replies -->
                @if($comment->replies->count() > 0)
                    <div class="mt-4 space-y-4 pl-6 border-l-2 border-gray-200">
                        @foreach($comment->replies as $reply)
                            <div class="bg-gray-50/60 backdrop-blur-sm rounded-lg p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-6 h-6 bg-gradient-to-r from-green-500 to-blue-500 rounded-full flex items-center justify-center text-white text-xs font-medium">
                                            {{ $reply->author->initials() }}
                                        </div>
                                        <div>
                                            <h5 class="text-sm font-medium text-[#231F20]">{{ $reply->author->name }}</h5>
                                            <p class="text-xs text-gray-500">{{ $reply->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                    
                                    @if($reply->author_id === auth()->id() || auth()->user()?->hasRole(['admin', 'developer']))
                                        <button
                                            wire:click="deleteComment({{ $reply->id }})"
                                            wire:confirm="Are you sure you want to delete this reply?"
                                            class="text-red-600 hover:text-red-800 text-xs"
                                        >
                                            Delete
                                        </button>
                                    @endif
                                </div>
                                
                                <p class="text-sm text-[#231F20]">{{ $reply->content }}</p>
                                
                                @auth
                                    <div class="flex items-center space-x-3 mt-2">
                                        <button
                                            wire:click="voteComment({{ $reply->id }}, 'upvote')"
                                            class="flex items-center space-x-1 text-xs {{ auth()->user()->hasVotedOnComment($reply) && auth()->user()->getCommentVote($reply)?->type === 'upvote' ? 'text-green-600' : 'text-gray-500 hover:text-green-600' }} transition-colors"
                                        >
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                            </svg>
                                            <span>{{ $reply->upvotes_count }}</span>
                                        </button>
                                        
                                        <button
                                            wire:click="voteComment({{ $reply->id }}, 'downvote')"
                                            class="flex items-center space-x-1 text-xs {{ auth()->user()->hasVotedOnComment($reply) && auth()->user()->getCommentVote($reply)?->type === 'downvote' ? 'text-red-600' : 'text-gray-500 hover:text-red-600' }} transition-colors"
                                        >
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                            <span>{{ $reply->downvotes_count }}</span>
                                        </button>
                                    </div>
                                @endauth
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="text-center py-12 bg-white/30 backdrop-blur-md rounded-xl border border-white/20">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <h3 class="text-lg font-medium text-[#231F20] mb-2">No comments yet</h3>
                <p class="text-gray-500">Be the first to share your thoughts!</p>
            </div>
        @endforelse
    </div>

    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show"
             class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('message') }}
        </div>
    @endif
</div>
