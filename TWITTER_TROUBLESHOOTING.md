# Twitter API Troubleshooting Guide

## Issues and Solutions

### 1. Rate Limiting (429 Too Many Requests)

**Problem**: You're getting "Too Many Requests" errors when trying to fetch Twitter mentions.

**Solutions**:
- **Wait 15 minutes** before making another API call
- **Use the cache** - mentions are cached for 15 minutes to reduce API calls
- **Check your Twitter API limits** in your Twitter Developer Portal
- **Use the force refresh sparingly** - only when you need the latest mentions

**What we've implemented**:
- Exponential backoff with jitter for retries
- 15-minute caching for mentions
- 1-minute minimum interval between API calls
- Rate limit protection in the UI

### 2. Self-Mentions Not Appearing

**Problem**: You mention yourself in posts but don't see them in the mentions page.

**Possible Causes**:
1. **Rate limiting** - API calls are failing due to 429 errors
2. **Indexing delay** - Twitter takes time to index new mentions
3. **API response issues** - The response structure might be different than expected
4. **Authentication problems** - Twitter tokens might be expired

**Troubleshooting Steps**:

#### Step 1: Test Twitter API Status
```bash
php artisan twitter:test-mentions
```

This command will:
- Check your Twitter connection status
- Test the API call directly
- Show detailed error information
- Verify your configuration

#### Step 2: Check Twitter Connection
- Go to your profile settings
- Verify Twitter account is connected
- Check if tokens are valid
- Reconnect if necessary

#### Step 3: Check Logs
```bash
tail -f storage/logs/laravel.log
```

Look for:
- `Fetching recent mentions` - API calls being made
- `Rate limited, waiting before retry` - Rate limiting issues
- `Twitter API Error fetching mentions` - API errors
- `Mentions API Response` - Response data

#### Step 4: Verify Configuration
Check your `.env` file has all required Twitter settings:
```env
X_API_KEY=your_api_key
X_API_KEY_SECRET=your_api_key_secret
X_ACCESS_TOKEN=your_access_token
X_ACCESS_TOKEN_SECRET=your_access_token_secret
X_BEARER_TOKEN=your_bearer_token
```

### 3. Configuration Options

You can customize Twitter API behavior by setting these environment variables:

```env
# Cache duration for mentions (in seconds, default: 900 = 15 minutes)
TWITTER_MENTIONS_CACHE_DURATION=900

# Minimum interval between API calls (in seconds, default: 60 = 1 minute)
TWITTER_MIN_INTERVAL_BETWEEN_CALLS=60

# Maximum retry attempts (default: 3)
TWITTER_MAX_RETRIES=3

# Base delay for retries (in seconds, default: 5)
TWITTER_BASE_RETRY_DELAY=5

# Rate limit cache duration (in seconds, default: 300 = 5 minutes)
TWITTER_RATE_LIMIT_CACHE_DURATION=300

# API timeout (in seconds, default: 30)
TWITTER_API_TIMEOUT=30

# Connection timeout (in seconds, default: 10)
TWITTER_CONNECT_TIMEOUT=10
```

### 4. Best Practices

1. **Don't refresh mentions too frequently** - Use the cache when possible
2. **Monitor your API usage** - Check Twitter Developer Portal for rate limits
3. **Use force refresh sparingly** - Only when you need the latest data
4. **Check logs regularly** - Monitor for errors and rate limiting
5. **Test with console command** - Use `php artisan twitter:test-mentions` for debugging

### 5. Common Error Messages

#### "Rate limit exceeded. Please wait a few minutes before trying again."
- **Solution**: Wait 15 minutes before making another call
- **Prevention**: Use caching and don't refresh too frequently

#### "Missing Twitter configuration: api_key, bearer_token"
- **Solution**: Check your `.env` file and ensure all Twitter settings are configured
- **Verification**: Use the test command to verify configuration

#### "User is not properly connected to Twitter"
- **Solution**: Reconnect your Twitter account in the app
- **Check**: Verify tokens haven't expired

### 6. Debugging Commands

```bash
# Test Twitter mentions API
php artisan twitter:test-mentions

# Test with specific user
php artisan twitter:test-mentions 1

# Clear all caches
php artisan cache:clear

# View recent logs
tail -f storage/logs/laravel.log
```

### 7. When to Contact Support

Contact support if you experience:
- Persistent 429 errors even after waiting 15+ minutes
- Authentication errors that persist after reconnecting
- API responses that don't match expected format
- Issues that persist after following all troubleshooting steps

### 8. Monitoring and Alerts

The system automatically:
- Logs all API calls and errors
- Implements exponential backoff for retries
- Caches responses to reduce API usage
- Prevents rapid successive API calls
- Provides detailed error messages in the UI

## Quick Fix Checklist

- [ ] Wait 15 minutes since last API call
- [ ] Check Twitter connection status
- [ ] Verify `.env` configuration
- [ ] Run `php artisan twitter:test-mentions`
- [ ] Check logs for specific errors
- [ ] Clear cache if needed
- [ ] Reconnect Twitter account if tokens expired 