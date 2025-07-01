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
        if ($comment->author_id !== auth()->id() && !auth()->user()->hasRole(['administrator', 'developer'])) {
            abort(403);
        }

        $comment->delete();
        $this->loadComments();
        
        session()->flash('message', 'Comment deleted successfully!');
    }
}; ?>

<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/10 dark:bg-yellow-400/5 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/5 rounded-full blur-3xl animate-pulse delay-1000"></div>
    </div>

    <div class="relative z-10 space-y-8 max-w-4xl mx-auto">
        {{-- Enhanced Comments Header with Glass Morphism --}}
        <section aria-labelledby="comments-heading" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 id="comments-heading" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">
                                    Discussion ({{ $comments->count() }})
                                </h3>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Join the conversation and share your insights</p>
                            </div>
                        </div>
                        
                        @if(!$showAll && $comments->count() >= $perPage)
                            <button 
                                wire:click="showAllComments"
                                class="group/btn relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-6 py-3"
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-blue-600/20 dark:from-blue-400/20 dark:to-blue-500/30 opacity-0 group-hover/btn:opacity-100 transition-opacity duration-300"></span>
                                <span class="relative text-[#231F20] dark:text-zinc-100 font-semibold text-sm">Show All Comments</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Comment Form with Modern Design --}}
        @auth
            <section aria-labelledby="add-comment-heading" class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    {{-- Animated Gradient Background --}}
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="relative p-8">
                        <div class="flex items-center space-x-4 mb-6">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                            </div>
                            <h4 id="add-comment-heading" class="text-lg font-bold text-[#231F20] dark:text-zinc-100">Share Your Thoughts</h4>
                        </div>

                        <form wire:submit.prevent="addComment" class="space-y-6">
                            <div>
                                <label for="newComment" class="sr-only">Add a comment</label>
                                <textarea
                                    wire:model.live="newComment"
                                    id="newComment"
                                    rows="4"
                                    class="w-full px-6 py-4 border border-white/20 dark:border-zinc-600/50 rounded-2xl focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-transparent resize-none bg-white/80 dark:bg-zinc-700/80 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 placeholder-[#9B9EA4] dark:placeholder-zinc-400 shadow-lg transition-all duration-300"
                                    placeholder="Share your thoughts, suggestions, or questions about this innovation..."
                                ></textarea>
                                @error('newComment') 
                                    <div class="mt-3 flex items-center space-x-2 text-red-600 dark:text-red-400">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm font-medium">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <div class="flex items-center space-x-2">
                                    <div class="w-2 h-2 {{ strlen($newComment) > 800 ? 'bg-red-500' : (strlen($newComment) > 600 ? 'bg-yellow-500' : 'bg-green-500') }} rounded-full"></div>
                                    <span class="text-sm text-[#9B9EA4] dark:text-zinc-400 font-medium">
                                        {{ 1000 - strlen($newComment) }} characters remaining
                                    </span>
                                </div>
                                <button
                                    type="submit"
                                    class="group/submit relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 to-blue-700 dark:from-blue-500 dark:to-blue-600 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-8 py-3 {{ strlen($newComment) < 3 ? 'opacity-50 cursor-not-allowed' : '' }}"
                                    wire:loading.attr="disabled"
                                    wire:target="addComment"
                                    @if(strlen($newComment) < 3) disabled @endif
                                >
                                    <span class="absolute inset-0 bg-gradient-to-br from-blue-700 to-blue-800 dark:from-blue-600 dark:to-blue-700 opacity-0 group-hover/submit:opacity-100 transition-opacity duration-300"></span>
                                    <span class="relative text-white font-semibold flex items-center space-x-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                        </svg>
                                        <span>Post Comment</span>
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        @endauth

        {{-- Enhanced Comments List --}}
        <section aria-labelledby="comments-list-heading" class="space-y-6">
            <h4 id="comments-list-heading" class="sr-only">Comments List</h4>
            
            @forelse($comments as $comment)
                <div class="group/comment relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl hover:shadow-2xl transition-all duration-500">
                    {{-- Comment Header --}}
                    <div class="p-8">
                        <div class="flex items-start justify-between mb-6">
                            <div class="flex items-center space-x-4">
                                <div class="relative">
                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 dark:from-blue-400 dark:to-purple-400 rounded-2xl flex items-center justify-center text-white text-sm font-bold shadow-lg">
                                        {{ $comment->author->initials() }}
                                    </div>
                                    <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-0 group-hover/comment:opacity-100 transition-opacity duration-500"></div>
                                </div>
                                <div>
                                    <h5 class="font-bold text-[#231F20] dark:text-zinc-100 text-lg">{{ $comment->author->name }}</h5>
                                    <div class="flex items-center space-x-2 text-sm text-[#9B9EA4] dark:text-zinc-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span>{{ $comment->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            @if($comment->author_id === auth()->id() || auth()->user()?->hasRole(['administrator', 'developer']))
                                <button
                                    wire:click="deleteComment({{ $comment->id }})"
                                    wire:confirm="Are you sure you want to delete this comment?"
                                    class="group/delete relative overflow-hidden rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 p-3"
                                >
                                    <span class="absolute inset-0 bg-gradient-to-br from-red-500/10 to-red-600/20 dark:from-red-400/20 dark:to-red-500/30 opacity-0 group-hover/delete:opacity-100 transition-opacity duration-300"></span>
                                    <svg class="relative w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            @endif
                        </div>

                        {{-- Comment Content --}}
                        <div class="mb-6 p-6 rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm">
                            <p class="text-[#231F20] dark:text-zinc-100 leading-relaxed text-lg">{{ $comment->content }}</p>
                        </div>

                        {{-- Enhanced Comment Actions --}}
                        @auth
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    {{-- Voting Section --}}
                                    <div class="flex items-center space-x-3 bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm rounded-2xl p-3">
                                        <button
                                            wire:click="voteComment({{ $comment->id }}, 'upvote')"
                                            class="group/upvote flex items-center space-x-2 text-sm {{ auth()->user()->hasVotedOnComment($comment) && auth()->user()->getCommentVote($comment)?->type === 'upvote' ? 'text-green-600 dark:text-green-400' : 'text-[#9B9EA4] dark:text-zinc-400 hover:text-green-600 dark:hover:text-green-400' }} transition-colors duration-300"
                                        >
                                            <div class="w-8 h-8 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700/50 flex items-center justify-center group-hover/upvote:shadow-lg transition-all duration-300">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                            <span class="font-semibold">{{ $comment->upvotes_count }}</span>
                                        </button>
                                        
                                        <button
                                            wire:click="voteComment({{ $comment->id }}, 'downvote')"
                                            class="group/downvote flex items-center space-x-2 text-sm {{ auth()->user()->hasVotedOnComment($comment) && auth()->user()->getCommentVote($comment)?->type === 'downvote' ? 'text-red-600 dark:text-red-400' : 'text-[#9B9EA4] dark:text-zinc-400 hover:text-red-600 dark:hover:text-red-400' }} transition-colors duration-300"
                                        >
                                            <div class="w-8 h-8 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/50 flex items-center justify-center group-hover/downvote:shadow-lg transition-all duration-300">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                            <span class="font-semibold">{{ $comment->downvotes_count }}</span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Reply Button --}}
                                <button
                                    wire:click="startReply({{ $comment->id }})"
                                    class="group/reply relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-white/20 dark:border-zinc-700/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-6 py-3"
                                >
                                    <span class="absolute inset-0 bg-gradient-to-br from-purple-500/10 to-purple-600/20 dark:from-purple-400/20 dark:to-purple-500/30 opacity-0 group-hover/reply:opacity-100 transition-opacity duration-300"></span>
                                    <span class="relative text-[#231F20] dark:text-zinc-100 font-semibold text-sm flex items-center space-x-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                        </svg>
                                        <span>Reply</span>
                                    </span>
                                </button>
                            </div>
                        @endauth

                        {{-- Enhanced Reply Form --}}
                        @auth
                            @if($replyingTo === $comment->id)
                                <div class="mt-8 p-6 rounded-2xl bg-gradient-to-r from-gray-50/80 to-gray-100/60 dark:from-zinc-800/80 dark:to-zinc-700/60 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm shadow-lg">
                                    <form wire:submit.prevent="addReply" class="space-y-4">
                                        <textarea
                                            wire:model="replyContent"
                                            rows="3"
                                            class="w-full px-4 py-3 border border-white/20 dark:border-zinc-600/50 rounded-2xl focus:ring-2 focus:ring-purple-500 dark:focus:ring-purple-400 focus:border-transparent resize-none bg-white/80 dark:bg-zinc-700/80 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 placeholder-[#9B9EA4] dark:placeholder-zinc-400"
                                            placeholder="Write your thoughtful reply..."
                                        ></textarea>
                                        @error('replyContent') 
                                            <div class="flex items-center space-x-2 text-red-600 dark:text-red-400">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </div>
                                        @enderror
                                        
                                        <div class="flex justify-end space-x-3">
                                            <button
                                                type="button"
                                                wire:click="cancelReply"
                                                class="px-6 py-2 text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-zinc-100 font-medium transition-colors duration-300"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                type="submit"
                                                class="px-6 py-2 bg-gradient-to-r from-purple-600 to-purple-700 dark:from-purple-500 dark:to-purple-600 text-white rounded-2xl hover:from-purple-700 hover:to-purple-800 dark:hover:from-purple-600 dark:hover:to-purple-700 transition-all duration-300 font-semibold shadow-lg"
                                            >
                                                Post Reply
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        @endauth

                        {{-- Enhanced Replies Section --}}
                        @if($comment->replies->count() > 0)
                            <div class="mt-8 space-y-4 pl-8 border-l-4 border-gradient-to-b from-[#FFF200]/30 to-[#F8EBD5]/30 dark:from-yellow-400/30 dark:to-amber-400/30">
                                @foreach($comment->replies as $reply)
                                    <div class="group/reply relative overflow-hidden rounded-2xl bg-gradient-to-r from-gray-50/80 to-gray-100/60 dark:from-zinc-800/80 dark:to-zinc-700/60 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm shadow-lg p-6">
                                        <div class="flex items-start justify-between mb-4">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-blue-500 dark:from-green-400 dark:to-blue-400 rounded-2xl flex items-center justify-center text-white text-sm font-bold shadow-lg">
                                                    {{ $reply->author->initials() }}
                                                </div>
                                                <div>
                                                    <h6 class="text-sm font-bold text-[#231F20] dark:text-zinc-100">{{ $reply->author->name }}</h6>
                                                    <div class="flex items-center space-x-1 text-xs text-[#9B9EA4] dark:text-zinc-400">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        <span>{{ $reply->created_at->diffForHumans() }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            @if($reply->author_id === auth()->id() || auth()->user()?->hasRole(['administrator', 'developer']))
                                                <button
                                                    wire:click="deleteComment({{ $reply->id }})"
                                                    wire:confirm="Are you sure you want to delete this reply?"
                                                    class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors duration-300"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                        
                                        <p class="text-sm text-[#231F20] dark:text-zinc-100 leading-relaxed mb-4">{{ $reply->content }}</p>
                                        
                                        @auth
                                            <div class="flex items-center space-x-3 bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm rounded-xl p-2">
                                                <button
                                                    wire:click="voteComment({{ $reply->id }}, 'upvote')"
                                                    class="flex items-center space-x-1 text-xs {{ auth()->user()->hasVotedOnComment($reply) && auth()->user()->getCommentVote($reply)?->type === 'upvote' ? 'text-green-600 dark:text-green-400' : 'text-[#9B9EA4] dark:text-zinc-400 hover:text-green-600 dark:hover:text-green-400' }} transition-colors duration-300"
                                                >
                                                    <div class="w-6 h-6 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700/50 flex items-center justify-center">
                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                                    </div>
                                                    <span class="font-semibold">{{ $reply->upvotes_count }}</span>
                                                </button>
                                                
                                                <button
                                                    wire:click="voteComment({{ $reply->id }}, 'downvote')"
                                                    class="flex items-center space-x-1 text-xs {{ auth()->user()->hasVotedOnComment($reply) && auth()->user()->getCommentVote($reply)?->type === 'downvote' ? 'text-red-600 dark:text-red-400' : 'text-[#9B9EA4] dark:text-zinc-400 hover:text-red-600 dark:hover:text-red-400' }} transition-colors duration-300"
                                                >
                                                    <div class="w-6 h-6 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/50 flex items-center justify-center">
                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </div>
                                                    <span class="font-semibold">{{ $reply->downvotes_count }}</span>
                                                </button>
                                            </div>
                                        @endauth
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    <div class="text-center py-16 relative">
                        {{-- Animated Background Element --}}
                        <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full -mr-32 -mt-32 blur-3xl"></div>
                        
                        <div class="relative z-10">
                            <div class="w-20 h-20 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-xl">
                                <svg class="w-10 h-10 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            
                            <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-4">Start the Conversation</h3>
                            <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg leading-relaxed max-w-md mx-auto">
                                No comments yet. Be the first to share your thoughts and spark meaningful discussions!
                            </p>
                        </div>
                    </div>
                </div>
            @endforelse
        </section>

        {{-- Enhanced Flash Messages --}}
        @if (session()->has('message'))
            <div x-data="{ show: true }" 
                 x-init="setTimeout(() => show = false, 4000)" 
                 x-show="show"
                 x-transition:enter="transition ease-out duration-500"
                 x-transition:enter-start="opacity-0 transform translate-y-2 scale-95"
                 x-transition:enter-end="opacity-100 transform translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100 transform translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 transform translate-y-2 scale-95"
                 class="fixed top-8 right-8 z-50 max-w-sm">
                <div class="relative overflow-hidden rounded-2xl bg-green-500/90 dark:bg-green-600/90 backdrop-blur-xl border border-green-400/20 dark:border-green-500/20 shadow-2xl p-6">
                    <div class="absolute inset-0 bg-gradient-to-br from-green-400/20 to-green-600/30 dark:from-green-300/20 dark:to-green-500/30"></div>
                    <div class="relative flex items-center space-x-3">
                        <div class="w-8 h-8 bg-white/20 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <p class="text-white font-semibold">{{ session('message') }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
